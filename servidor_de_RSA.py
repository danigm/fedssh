#!/usr/bin/python

import sys
import socket
import pdb
from threading import Thread
#import peoplefinder

'''
interfaz para peopleFinder
class iPeopleFinder():
    def __init__(self, resource):
        pass
    def get_rsa(self, user):
        pass
'''

class PeopleFinder:
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


class Responser(Thread):
    def __init__ (self, sock, addr):
        Thread.__init__(self)
        self.sock = sock
        self.sock.settimeout(60)
        self.addr = addr
        self.buffer = ''
        self.msg = []
        self.pfinder = PeopleFinder('applog')

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
            print "Fallo al intenter contestar a los mensajes"

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
                self.close_all()
                print "final de ejecucion"
                sys.exit(0)

    def close_all(self):
        self.ss.close()

if __name__ == "__main__" :
    srsa = ServidorRSA()
