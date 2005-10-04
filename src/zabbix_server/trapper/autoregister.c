/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "config.h"

#include <stdio.h>
#include <stdlib.h>

#include <string.h>

#include "cfg.h"
#include "pid.h"
#include "db.h"
#include "log.h"
#include "zlog.h"

#include <sys/types.h>
#include <regex.h>

#include "common.h"


int	autoregister(char *server)
{
	DB_RESULT	*result;

	int	ret=SUCCEED;
	char	sql[MAX_STRING_LEN];
	char	*pattern;
	int	i;
	int	len;
	int	hostid;
	
	zabbix_log( LOG_LEVEL_WARNING, "In autoregister(%s)",server);

	if(DBhost_exists(server) == SUCCEED)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Host [%d] already exists. Do nothing.", server);
		return FAIL;
	}

	snprintf(sql,sizeof(sql)-1,"select id,pattern,hostid from autoreg order by priority");

	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		pattern=DBget_field(result,i,1);
		hostid=atoi(DBget_field(result,i,2));

		if(zbx_regexp_match(server, pattern, &len) != 0)
		{
			zabbix_log( LOG_LEVEL_WARNING, "Matched [%s] [%s]",server,pattern);
			register_new_host(server, hostid);
			break;
		}
		else
		{
			zabbix_log( LOG_LEVEL_WARNING, "No match [%s] [%s]",server,pattern);
		}
	}

	DBfree_result(result);

	return ret;
}

void	register_new_host(char *server, int hostid)
{
	int	id;

	zabbix_log( LOG_LEVEL_WARNING, "In register_new_host(%s,%d)", server, hostid);

	id = DBadd_new_host(server, 10050, HOST_STATUS_MONITORED, 0, "", 0, HOST_AVAILABLE_UNKNOWN);
}
