#!/usr/bin/python

import sys
import urllib
import urllib2
import json

if len(sys.argv) < 4:
	print """arguments: host path file [httpauthuser:password]
For example:
  ./inject.py listeners.localweb /smashpig/amazon/listener Tests/Data/IPN/SubscriptionSuccessful.json
"""
	exit(-1)

headers = {}
if len(sys.argv) > 4:
	import base64
	auth = base64.encodestring(sys.argv[4]).replace("\n", "")
	headers['Authorization'] = "Basic " + auth

data = urllib.urlencode(json.load(open(sys.argv[3], "r")))

req = urllib2.Request("http://%s%s" % (sys.argv[1], sys.argv[2]), data, headers)
urllib2.urlopen(req)
