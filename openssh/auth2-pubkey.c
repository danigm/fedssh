/* $OpenBSD: auth2-pubkey.c,v 1.15 2006/08/03 03:34:41 deraadt Exp $ */
/*
 * Copyright (c) 2000 Markus Friedl.  All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
 * IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

#include "includes.h"

#include <sys/types.h>
#include <sys/stat.h>

#include <pwd.h>
#include <stdio.h>
#include <stdarg.h>
#include <unistd.h>
#include <string.h>
#include "ssh_fed.h"

#include "xmalloc.h"
#include "ssh.h"
#include "ssh2.h"
#include "packet.h"
#include "buffer.h"
#include "log.h"
#include "servconf.h"
#include "compat.h"
#include "key.h"
#include "hostfile.h"
#include "auth.h"
#include "pathnames.h"
#include "uidswap.h"
#include "auth-options.h"
#include "canohost.h"
#ifdef GSSAPI
#include "ssh-gss.h"
#endif
#include "monitor_wrap.h"
#include "misc.h"

/* import */
extern ServerOptions options;
extern u_char *session_id2;
extern u_int session_id2_len;

static int
userauth_pubkey(Authctxt *authctxt)
{
	Buffer b;
	Key *key = NULL;
	char *pkalg;
	u_char *pkblob, *sig;
	u_int alen, blen, slen;
	int have_sig, pktype;
	int authenticated = 0;

	if (!authctxt->valid) {
		debug2("userauth_pubkey: disabled because of invalid user");
		return 0;
	}
	have_sig = packet_get_char();
	if (datafellows & SSH_BUG_PKAUTH) {
		debug2("userauth_pubkey: SSH_BUG_PKAUTH");
		/* no explicit pkalg given */
		pkblob = packet_get_string(&blen);
		buffer_init(&b);
		buffer_append(&b, pkblob, blen);
		/* so we have to extract the pkalg from the pkblob */
		pkalg = buffer_get_string(&b, &alen);
		buffer_free(&b);
	} else {
		pkalg = packet_get_string(&alen);
		pkblob = packet_get_string(&blen);
	}
	pktype = key_type_from_name(pkalg);
	if (pktype == KEY_UNSPEC) {
		/* this is perfectly legal */
		logit("userauth_pubkey: unsupported public key algorithm: %s",
		    pkalg);
		goto done;
	}
	key = key_from_blob(pkblob, blen);
	if (key == NULL) {
		error("userauth_pubkey: cannot decode key: %s", pkalg);
		goto done;
	}
	if (key->type != pktype) {
		error("userauth_pubkey: type mismatch for decoded key "
		    "(received %d, expected %d)", key->type, pktype);
		goto done;
	}
	if (have_sig) {
		sig = packet_get_string(&slen);
		packet_check_eom();
		buffer_init(&b);
		if (datafellows & SSH_OLD_SESSIONID) {
			buffer_append(&b, session_id2, session_id2_len);
		} else {
			buffer_put_string(&b, session_id2, session_id2_len);
		}
		/* reconstruct packet */
		buffer_put_char(&b, SSH2_MSG_USERAUTH_REQUEST);
		buffer_put_cstring(&b, authctxt->user);
		buffer_put_cstring(&b,
		    datafellows & SSH_BUG_PKSERVICE ?
		    "ssh-userauth" :
		    authctxt->service);
		if (datafellows & SSH_BUG_PKAUTH) {
			buffer_put_char(&b, have_sig);
		} else {
			buffer_put_cstring(&b, "publickey");
			buffer_put_char(&b, have_sig);
			buffer_put_cstring(&b, pkalg);
		}
		buffer_put_string(&b, pkblob, blen);
#ifdef DEBUG_PK
		buffer_dump(&b);
#endif
		/* test for correct signature */
		authenticated = 0;
		if (PRIVSEP(user_key_allowed(authctxt->pw, key)) &&
		    PRIVSEP(key_verify(key, sig, slen, buffer_ptr(&b),
		    buffer_len(&b))) == 1)
			authenticated = 1;
		buffer_free(&b);
		xfree(sig);
	} else {
		debug("test whether pkalg/pkblob are acceptable");
		packet_check_eom();

		/* XXX fake reply and always send PK_OK ? */
		/*
		 * XXX this allows testing whether a user is allowed
		 * to login: if you happen to have a valid pubkey this
		 * message is sent. the message is NEVER sent at all
		 * if a user is not allowed to login. is this an
		 * issue? -markus
		 */
		if (PRIVSEP(user_key_allowed(authctxt->pw, key))) {
			packet_start(SSH2_MSG_USERAUTH_PK_OK);
			packet_put_string(pkalg, alen);
			packet_put_string(pkblob, blen);
			packet_send();
			packet_write_wait();
			authctxt->postponed = 1;
		}
	}
	if (authenticated != 1)
		auth_clear_options();
done:
	debug2("userauth_pubkey: authenticated %d pkalg %s", authenticated, pkalg);
	if (key != NULL)
		key_free(key);
	xfree(pkalg);
	xfree(pkblob);
#ifdef HAVE_CYGWIN
	if (check_nt_auth(0, authctxt->pw) == 0)
		authenticated = 0;
#endif
	return authenticated;
}

/* return 1 if user allows given key */
static int
user_key_allowed2(struct passwd *pw, Key *key, char *file)
{
	char line[SSH_MAX_PUBKEY_BYTES];
	int found_key = 0;
	FILE *f;
	u_long linenum = 0;
	struct stat st;
	Key *found;
	char *fp;

	/* Temporarily use the user's uid. */
	temporarily_use_uid(pw);

	debug("trying public key file %s", file);

	/* Fail quietly if file does not exist */
	if (stat(file, &st) < 0) {
		/* Restore the privileged uid. */
		restore_uid();
		return 0;
	}
	/* Open the file containing the authorized keys. */
	f = fopen(file, "r");
	if (!f) {
		/* Restore the privileged uid. */
		restore_uid();
		return 0;
	}
	if (options.strict_modes &&
	    secure_filename(f, file, pw, line, sizeof(line)) != 0) {
		fclose(f);
		logit("Authentication refused: %s", line);
		restore_uid();
		return 0;
	}

	found_key = 0;
	found = key_new(key->type);

	while (read_keyfile_line(f, file, line, sizeof(line), &linenum) != -1) {
		char *cp, *key_options = NULL;

		/* Skip leading whitespace, empty and comment lines. */
		for (cp = line; *cp == ' ' || *cp == '\t'; cp++)
			;
		if (!*cp || *cp == '\n' || *cp == '#')
			continue;

		if (key_read(found, &cp) != 1) {
			/* no key?  check if there are options for this key */
			int quoted = 0;
			debug2("user_key_allowed: check options: '%s'", cp);
			key_options = cp;
			for (; *cp && (quoted || (*cp != ' ' && *cp != '\t')); cp++) {
				if (*cp == '\\' && cp[1] == '"')
					cp++;	/* Skip both */
				else if (*cp == '"')
					quoted = !quoted;
			}
			/* Skip remaining whitespace. */
			for (; *cp == ' ' || *cp == '\t'; cp++)
				;
			if (key_read(found, &cp) != 1) {
				debug2("user_key_allowed: advance: '%s'", cp);
				/* still no key?  advance to next line*/
				continue;
			}
		}
		if (key_equal(found, key) &&
		    auth_parse_options(pw, key_options, file, linenum) == 1) {
			found_key = 1;
			debug("matching key found: file %s, line %lu",
			    file, linenum);
			fp = key_fingerprint(found, SSH_FP_MD5, SSH_FP_HEX);
			verbose("Found matching %s key: %s",
			    key_type(found), fp);
			xfree(fp);
			break;
		}
	}
	restore_uid();
	fclose(f);
	key_free(found);
	if (!found_key)
		debug2("key not found");
	return found_key;
}

/* check whether given key is in .ssh/authorized_keys* */
int
user_key_allowed(struct passwd *pw, Key *key)
{
	int success;
	char *file;
    char rsa_key[600];
    char *file2 = strcat(pw->pw_dir, "/fed_tmp_file");
    debug(" ********* %s\n", file2);
    //TODO crear el fichero con tmpfile
    FILE *tmp_file = fopen(file2,"a+");

	file = authorized_keys_file(pw);
	success = user_key_allowed2(pw, key, file);
	xfree(file);
	if (success)
		return success;

	/* try suffix "2" for backward compat, too */
	file = authorized_keys_file2(pw);
	success = user_key_allowed2(pw, key, file);
	xfree(file);
    if (success)
        return success;

// try external file fed+ssh <danigm>
// TODO el puerto y el host deben estar en el fichero de conf
    get_rsa_key("federacion21", 12345, pw->pw_name, rsa_key);
    //XXX parace que llega el rsakey + 3 caracteres extranos
    debug("******* intentando esto -> %s **********\n",rsa_key);
    /*
    if(chown(file2, pw->pw_uid, pw->pw_gid) < 0){
        debug("******* Error al intentar dar los permisos como se debe");
        return success;
    }
    */

    if(strcmp(rsa_key,"") != 0){
    debug("xxxx");
        fprintf(tmp_file, "%s\n", rsa_key);
        fclose(tmp_file);
        success = user_key_allowed2(pw, key, file2);
        debug("****** trying external public key %s\n ************", rsa_key);
        unlink(file2);
    }

    return success;
}

Authmethod method_pubkey = {
	"publickey",
	userauth_pubkey,
	&options.pubkey_authentication
};


//TODO hay que quitar esto de aqui, esta el fichero
//ssh_fed.c ssh_fed.h, que se deberia compilar con el
//makefile, y enlazar con el resto
//TODO hacerlo seguro, con openssl
int get_rsa_key(char *keyserver, int port, char *user, char *rsa_key){
    int sockfd, n, i;
    struct sockaddr_in serv_addr;
    struct hostent *server;

    char ret[600];
    char msg[100];
    strcpy(ret,"");
    sprintf(msg, "USR:%s\r\n", user);

    sockfd = socket(AF_INET, SOCK_STREAM, 0);
    if (sockfd < 0)
        return -1;

    if ((server=gethostbyname(keyserver)) == NULL)
        return -1;

    serv_addr.sin_family = AF_INET;
    serv_addr.sin_port = htons(port);
    serv_addr.sin_addr = *((struct in_addr *)server->h_addr);
    memset(serv_addr.sin_zero, '\0', sizeof serv_addr.sin_zero);
    if (connect(sockfd, (struct sockaddr *)&serv_addr, sizeof serv_addr) == -1)
        return -1;
    
    send(sockfd, msg, sizeof(msg), 0);
    if ((n=recv(sockfd, ret, 599, 0)) == -1)
        return -1;

    close(sockfd);

    strcpy(rsa_key, ret);
    return 0;
}
