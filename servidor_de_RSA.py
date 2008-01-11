#!/usr/bin/python

import sys
import socket
import pdb
from threading import Thread
import MySQLdb
#import peoplefinder
import threading
import datetime

# Variables de configuracion para acceso a base de datos
HOST = '127.0.0.1'
USER = 'federacionssh'
PASS = 'fedssh.[pass]'
DB = 'federacionssh'

'''
interfaz para peopleFinder
class iPeopleFinder():
    def __init__(self, resource):
        pass
    def get_rsa(self, user):
        pass
'''

class PeopleFinder():
    '''
    Buscamos en un fichero de texto plano
    '''
    def __init__(self, resource="db.txt"):
        self.resource = resource

    def get_rsa(self, user):
        to_ret = ''
        for line in open(self.resource):
            usr = line[0:line.find(':')]
            if user == usr:
                to_ret = line[line.find(':')+1:]

        if to_ret == '':
            to_ret = 'NOT FOUND'
            
        return to_ret

class PeopleFinderdb():
    '''
    Buscamos en una base de datos mysql
    '''
    def __init__(self, host, user, password, db):
        self.host = host
        self.user = user
        self.password = password
        self.db = db

    def get_rsa(self, user):
        to_ret = ''
        try:
            db=MySQLdb.connect(host=self.host,user=self.user,passwd=self.password,db=self.db)
            cursor = db.cursor()
            q = "select pubkey from pubkey where uid='"+user+"'"
            lines = cursor.execute(q)
            if(lines):
                res = cursor.fetchall()
                to_ret = res[0][0]
        except:
            pass
        return to_ret

def timeout_check():
    tiempo = 1
    try:
        db=MySQLdb.connect(HOST, USER, PASS, DB)
        cursor = db.cursor()
        # TODO aqui hay que poner un limite, porque no vamos a hacer
        # consultas gigantescas cada cierto tiempo
        q = "select init,timeout,uid from pubkey"
        lines = cursor.execute(q)
        if(lines):
            res = cursor.fetchall()
            for inicio, t, user in res:
                delta = inicio.now() - inicio
                delta = delta.seconds / 60
                if delta > t:
                    q = "delete from pubkey where uid = '" + user +"'"
                    cursor.execute(q)
    except:
        pass
    if HOST != "":
        t = threading.Timer(tiempo*60, timeout_check)
        t.start()

class Responser(Thread):
    def __init__ (self, sock, addr):
        Thread.__init__(self)
        self.sock = sock
        self.sock.settimeout(60)
        self.addr = addr
        self.buffer = ''
        self.msg = []
        self.pfinder = PeopleFinderdb(HOST,USER,PASS,DB)

    def run(self):
        print "inicio de " + str(self.addr) + "\n"
        while self.recv() and len(self.msg) > 0:
            command = self.msg.pop(0)
            while (command == '' and len(self.msg) > 0):
                command = self.msg.pop(0)
            if command == '': break
            if command[0:3].upper() == 'USR':
                user = command[4:]
                self.send(self.find(user))
            if command[0:3].upper() == 'XIT':
                break
        self.sock.close()
        print "fin de " + str(self.addr) + "\n"

    def recv(self):
        try:
            self.buffer = self.sock.recv(1024)
            self.msg.extend(self.buffer.split('\r\n'))
            return True
        except:
            return False

    def send(self, msg):
        try:
            self.sock.send(msg+'\r\n')
            return True
        except:
            return False

    def find(self, user):
        '''
        Busca un usuario en el almacen, y devuelve la clave RSA
        asociada. Si no lo encuentra, devuelve ''
        '''
        return self.pfinder.get_rsa(user)





class ServidorRSA:
    '''
    Servidor de claves RSA.
    Tiene un almacen de claves por usuarios autenticados en la
    federacion.
    Espera una peticion de usuario, y contesta con la clave RSA si lo
    tiene en el almacen.
    '''
    def __init__(self):
        timeout_check()
        self.port = 12345
        self.ss = socket.socket()
        try:
            self.ss.bind(('', self.port))
            self.ss.listen(5)
        except:
            print "Fallo al iniciar el servicio en el puerto %d" % \
            self.port
        try:
       	    self.respond()
        except:
            print "Fallo al intentar contestar a los mensajes"

    def respond(self):
        '''
        Espera una conexion, y cuando la recibe crea un thread para
        manejarla.
        '''
        while True:
            try:
                new_sock, new_addr = self.ss.accept()
                res = Responser(new_sock, new_addr)
                res.start()
            except:
                global HOST
                self.close_all()
                HOST = ""
                print "final de ejecucion"
                sys.exit(0)

    def close_all(self):
        self.ss.close()

if __name__ == "__main__" :
    srsa = ServidorRSA()
