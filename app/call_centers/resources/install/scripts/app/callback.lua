--[[
Steps:
function to collect userinfo.  Needs to take channel vars and DB vars
    so that we know who to call back and how to call them

looping function to play back queue position (probably needs another file)

Way to capture channel var cc_exit_key and send to IVR

looping function to monitor queue cc-base-score
    if the top user has a base score lower than my score (using os.time - start_epoch), and caller is not currently in queue, then call back and add to queue with my cc-base-score
        If noone in queue then callback
            
Need to figure out how this plays with abandoned

        freeswitch@fusion.corpit.xyz> callcenter_config queue list members 60001@fusion.corpit.xyz
queue|instance_id|uuid|session_uuid|cid_number|cid_name|system_epoch|joined_epoch|rejoined_epoch|bridge_epoch|abandoned_epoch|base_score|skill_score|serving_agent|serving_system|state|score
60001@fusion.corpit.xyz|single_box|5cfc0fee-3c95-4809-a32c-41379ade5f74|85c4cb75-e0fe-42ee-8203-ab501c11ad69|14013452580|WIRELESS CALLER|1671466072|1671466077|0|0|0|105|0|||Waiting|105
+OK


function to call back user using gateway and DB vars for caller ID


Need to create table with domain, which queue in, position, entry time, how many attempts to call back
Need a UI to set up callback options
Need to allow breakout from queue to request callback
]]