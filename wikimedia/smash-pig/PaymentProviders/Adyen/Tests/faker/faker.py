#!/usr/bin/env python
# -*- coding: UTF-8 -*-
import re
import sys 
import random
import os

if len(sys.argv) > 1:
    num = int(sys.argv[1]);
else:
    num = 5;

templatedir = os.path.dirname(os.path.realpath(__file__)) + '/templates/'

with open(templatedir + 'ipnContainer.xml', 'r') as myfile:
    ipnContainer = myfile.read()

with open(templatedir + 'auth.xml', 'r') as myfile:
    auth = myfile.read()

with open(templatedir + 'capture.xml', 'r') as myfile:
    capture = myfile.read()

with open(templatedir + 'pending.json', 'r') as myfile:
    pending = myfile.read()

auths = ''
captures = ''
pqueue = ''

fileprefix = 'adyen-' + str(random.randint(10000,100000))

for x in range(0, num):
    ctid = str(random.randint(100000,1000000))
    oid = ctid + '.' + str(random.randint(0,4))
    authid = str(random.randint(100000000000,1000000000000))
    captureid = str(random.randint(100000000000,1000000000000))
    header = 'adyen-'  + oid
    pqueue += pending.replace('[[CTID]]', ctid).replace('[[ORDERID]]', oid)
    auths += auth.replace('[[ORDERID]]', oid).replace('[[AUTHID]]', authid)
    captures += capture.replace('[[ORDERID]]', oid).replace('[[AUTHID]]', authid).replace('[[CAPTUREID]]', captureid)

with open(fileprefix + '-auths.xml', 'w') as myfile:
    myfile.write( ipnContainer.replace('[[ITEMS]]', auths ))

with open(fileprefix + '-captures.xml', 'w') as myfile:
    myfile.write( ipnContainer.replace('[[ITEMS]]', captures ))

with open(fileprefix + '-pending.json', 'w') as myfile:
    myfile.write( pqueue )

print('Dumped {0} fake donations in your lap. Bon appétit!'.format(num))
