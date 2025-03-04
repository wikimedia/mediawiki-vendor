#!/usr/bin/python

import sys
import urllib
import urllib2
import json

if len(sys.argv) < 4:
	print """arguments: host path file [httpauthuser:password]
For example:
  ./inject.py listeners.localweb /smashpig/globalcollect/listener Tests/Data/PSC/PaymentNoCCDetails.json
"""
	exit(-1)

headers = {}
if len(sys.argv) > 4:
	import base64
	auth = base64.encodestring(sys.argv[4]).replace("\n", "")
	headers['Authorization'] = "Basic " + auth

data = urllib.urlencode(json.load(open(sys.argv[3], "r")))

req = urllib2.Request("http://%s%s" % (sys.argv[1], sys.argv[2]), data, headers)
out = urllib2.urlopen(req)
print "Output: ", out.read()
