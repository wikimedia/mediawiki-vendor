#!/usr/bin/python

import sys
import csv
import httplib
import xml.dom.minidom
from xml.parsers.expat import ExpatError
from dateutil.parser import parse

pre = r"""<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Body><ns1:sendNotification xmlns:ns1="http://notification.services.adyen.com"><ns1:notification><live xmlns="http://notification.services.adyen.com">true</live><notificationItems xmlns="http://notification.services.adyen.com">"""
msg = r"""<NotificationRequestItem><additionalData xsi:nil="true" /><amount><currency xmlns="http://common.services.adyen.com">%(currency)s</currency><value xmlns="http://common.services.adyen.com">%(amount)s</value></amount><eventCode>AUTHORISATION</eventCode><eventDate>%(date)s</eventDate><merchantAccountCode>WikimediaCOM</merchantAccountCode><merchantReference>%(orderid)s</merchantReference><operations><string>CANCEL</string><string>CAPTURE</string><string>REFUND</string></operations><originalReference xsi:nil="true" /><paymentMethod>discover</paymentMethod><pspReference>%(pspreference)s</pspReference><reason>01653R:6389:5/2018</reason><success>true</success></NotificationRequestItem>"""
post = r"""</notificationItems></ns1:notification></ns1:sendNotification></soap:Body></soap:Envelope>"""

if len(sys.argv) != 4:
    print "Requires 3 argument: host path datafile.csv"
    exit(-1)

# Construct the request
msgdoc = [pre]
with open(sys.argv[3], 'r') as csvfile:
    f = csv.reader(csvfile)
    for row in f:
        msgdoc.append(msg % {
            'pspreference': row[0],
            'orderid': row[1],
            'date': parse(row[2]).isoformat(),
            'currency': row[3].split()[0],
            'amount': row[3].split()[1]
        })
    msgdoc.append(post)
msgdoc = "".join(msgdoc)

conn = httplib.HTTPSConnection(sys.argv[1])

conn.connect()
conn.request('POST', sys.argv[2], msgdoc)
req = conn.getresponse().read()

try:
    dom = xml.dom.minidom.parseString(req)
    print (dom.toprettyxml())
except ExpatError:
    # Hurm... not valid XML eh?
    print (req)

conn.close()
