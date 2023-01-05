#!/usr/bin/python

import json
import xml.dom.minidom
import sys

jstring = open(sys.argv[1], 'r').read()

xmlstring = json.loads(jstring)

dom = xml.dom.minidom.parseString(xmlstring)

f = open(sys.argv[2], 'w')
f.write(dom.toprettyxml())
f.close()

