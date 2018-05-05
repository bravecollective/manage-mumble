#!/usr/bin/env python

import re
import sys
import time
import ConfigParser
import json
from operator import itemgetter, attrgetter, methodcaller

import Ice
#Ice.loadSlice('-I/usr/share/Ice-3.5.1/slice/ /usr/share/slice/Murmur.ice')
Ice.loadSlice(b'', [b'-I' + (Ice.getSliceDir() or b'/usr/local/share/Ice-3.5/slice/'), b'Murmur.ice'])

import Murmur
from Murmur import Ban

# -------------------------------------------------------------------------------

cfg = 'mumble-sso-core-admin.ini'
#print('Reading config file: {0}').format(cfg)
config = ConfigParser.RawConfigParser()
config.read(cfg)

server_id = config.getint('murmur', 'server_id')
ice_host = config.get('murmur', 'ice_host')
ice_port = config.get('murmur', 'ice_port')
ice_secret = config.get('murmur', 'ice_secret')

# -------------------------------------------------------------------------------

pok = None

if len(sys.argv) == 2 and sys.argv[1] == 'users':
    arg_action = sys.argv[1]
    pok = 1

if len(sys.argv) == 2 and sys.argv[1] == 'bans':
    arg_action = sys.argv[1]
    pok = 1

if len(sys.argv) == 4 and sys.argv[1] == 'kick':
    arg_action = sys.argv[1]
    arg_userid = int(sys.argv[2])
    arg_reason = sys.argv[3]
    pok = 1

if len(sys.argv) == 5 and sys.argv[1] == 'kickban':
    arg_action = sys.argv[1]
    arg_userid = int(sys.argv[2])
    arg_length = int(sys.argv[3])
    arg_reason = sys.argv[4]
    pok = 1

if len(sys.argv) == 3 and sys.argv[1] == 'unban':
    arg_action = sys.argv[1]
    arg_username = sys.argv[2]
    pok = 1

if not pok:
    print sys.argv[0] + " users"
    print sys.argv[0] + " bans"
    print sys.argv[0] + " kick <userid> <reason>"
    print sys.argv[0] + " kickban <userid> <length> <reason>"
    print sys.argv[0] + " unban <username>"
    sys.exit(1)

# -------------------------------------------------------------------------------

# Init ice
prop = Ice.createProperties()
prop.setProperty("Ice.ImplicitContext", "Shared")
prop.setProperty("Ice.MessageSizeMax", "2097152");
idd = Ice.InitializationData()
idd.properties = prop
comm = Ice.initialize(idd)
comm.getImplicitContext().put("secret", ice_secret)
proxy = comm.stringToProxy('Meta:tcp -h ' + ice_host + ' -p ' + ice_port)
meta = Murmur.MetaPrx.checkedCast(proxy)
server = meta.getServer(server_id)

if arg_action == 'users':
    users = []
    for session_id, user in server.getUsers().iteritems():
	users.append({'id': user.userid, 'name': user.name})
    users = sorted(users, key=lambda user: user['name'])
    print(json.dumps(users))

if arg_action == 'bans':
    bans = []
    for ban in server.getBans():
	if not ban.name:
	    continue
	bans.append({'name': ban.name, 'reason': ban.reason, 'start': ban.start, 'duration': ban.duration})
    bans = sorted(bans, key=lambda ban: ban['start'])
    print(json.dumps(bans))

if arg_action == 'kick':
    for session_id, user in server.getUsers().iteritems():
	if user.userid == arg_userid:
	    server.kickUser(session_id, arg_reason)
    print(json.dumps("OK"));

if arg_action == 'kickban':
    bans = server.getBans()
    for session_id, user in server.getUsers().iteritems():
	if user.userid == arg_userid:
	    ban_args = dict()
	    ban_args['address'] = user.address
	    ban_args['bits'] = 128
	    ban_args['name'] = user.name
	    ban_args['start'] = int(time.time())
	    ban_args['duration'] = arg_length
	    ban_args['reason'] = arg_reason
	    ban = Ban(**ban_args)

	    ban_len = "{0} s".format(arg_length)
	    if (arg_length > 60):
		ban_len = "{0} m".format(arg_length / 60)
	    if (arg_length > 60 * 60):
		ban_len = "{0} h".format(arg_length / 60 / 60)
	    if (arg_length > 60 * 60 * 24):
		ban_len = "{0} d".format(arg_length / 60 / 60 / 24)

	    bans.append(ban)
	    server.kickUser(session_id, "BANNED ({0}): {1}".format(ban_len, arg_reason))
    server.setBans(bans)
    print(json.dumps("OK"));

if arg_action == 'unban':
    bans = server.getBans()
    for ban in bans:
	if ban.name == arg_username:
	    bans.remove(ban)
    server.setBans(bans)
    print(json.dumps("OK"));

comm.shutdown()
