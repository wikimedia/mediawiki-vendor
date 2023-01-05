#!/usr/bin/python

import sys
import httplib
import xml.dom.minidom
from xml.parsers.expat import ExpatError

if len(sys.argv) != 4:
	print "Requires 3 arguments: host path file"
	exit(-1)

conn = httplib.HTTPConnection(sys.argv[1])

conn.connect()
conn.request('POST', sys.argv[2], open(sys.argv[3], 'r').read())
req = conn.getresponse().read()

try:
	dom = xml.dom.minidom.parseString(req)
	print (dom.toprettyxml())
except ExpatError:
	# Hurm... not valid XML eh?
	print (req)

conn.close


