#!/usr/bin/env python

from twisted.words.protocols import irc
from twisted.internet.protocol import ReconnectingClientFactory
import os
import os.path
import threading
import select
import signal
import sys
import time
from threading import Thread

irc_server = "192.168.0.10"
irc_port = 6667

irc_nickname = "name"
irc_realname = irc_nickname

irc_channel = "#test"
irc_channel_password = ""

fifo = "/tmp/gitbot_fifo"

fork_to_background = True

class IRCClient(irc.IRCClient):
    nickname = irc_nickname
    realname = irc_realname
    channel = irc_channel
    channelpass = irc_channel_password

    instance = None

    def signedOn(self):
        print("connected!")
        IRCClient.instance = self
        self.join(self.channel, self.channelpass)

    def message(self, message):
        self.say(self.channel, message)

    def threadmsg(self, message):
        reactor.callFromThread(self.msg, self.channel, message)

def signalhandler(signum = None, frame = None):
    os._exit(0)

def checkfifo():

    io = None

    while io is None:
        try:
            if os.path.exists(fifo):
                os.remove(fifo)

            os.mkfifo(fifo, 0666)
            io = os.open(fifo, os.O_RDONLY|os.O_NONBLOCK)
            os.fchmod(io, 0666) # why...?
        except:
            time.sleep(1)
            continue

    while True:
        r,w,x = select.select([io], [], [], 1)
        if r:
            buffer = os.read(io, 4096)

            if not buffer is None: 
                if not IRCClient.instance is None:
                    for line in buffer.split('\n'):
                        line.replace('\r', '')
                        IRCClient.instance.threadmsg(line)

            os.close(io)

            try:
                io = os.open(fifo, os.O_RDONLY|os.O_NONBLOCK)
            except:
                pass

if __name__ == "__main__":
    from twisted.internet import reactor

    if fork_to_background is True:
        print("forking to background...")

        if os.fork():
            sys.exit()

        os.close(0)
        os.close(1)
        os.close(2)

    signal.signal(signal.SIGTERM, signalhandler)
    signal.signal(signal.SIGINT, signalhandler)

    factory = ReconnectingClientFactory()
    factory.protocol = IRCClient
    reactor.connectTCP(irc_server, irc_port, factory)

    thread = Thread(target = checkfifo, args = ())
    thread.start()

    reactor.run()

