<?php

define('REMARKNAME', '@KevinVPN');

function guid() {
    $randomString = openssl_random_pseudo_bytes(16);
    $time_low = bin2hex(substr($randomString, 0, 4));
    $time_mid = bin2hex(substr($randomString, 4, 2));
    $time_hi_and_version = bin2hex(substr($randomString, 6, 2));
    $clock_seq_hi_and_reserved = bin2hex(substr($randomString, 8, 2));
    $node = bin2hex(substr($randomString, 10, 6));

    /**
     * Set the four most significant bits (bits 12 through 15) of the
     * time_hi_and_version field to the 4-bit version number from
     * Section 4.1.3.
     * @see http://tools.ietf.org/html/rfc4122#section-4.1.3
     */
    $time_hi_and_version = hexdec($time_hi_and_version);
    $time_hi_and_version = $time_hi_and_version >> 4;
    $time_hi_and_version = $time_hi_and_version | 0x4000;

    /**
     * Set the two most significant bits (bits 6 and 7) of the
     * clock_seq_hi_and_reserved to zero and one, respectively.
     */
    $clock_seq_hi_and_reserved = hexdec($clock_seq_hi_and_reserved);
    $clock_seq_hi_and_reserved = $clock_seq_hi_and_reserved >> 2;
    $clock_seq_hi_and_reserved = $clock_seq_hi_and_reserved | 0x8000;

    return sprintf('%08s-%04s-%04x-%04x-%012s', $time_low, $time_mid, $time_hi_and_version, $clock_seq_hi_and_reserved, $node);
} // guid
function get_web_page2($url) {
    $options = array(
        CURLOPT_RETURNTRANSFER => true, // return web page
        CURLOPT_HEADER => false, // don't return headers
        CURLOPT_FOLLOWLOCATION => true, // follow redirects
        CURLOPT_ENCODING => "", // handle all encodings
        CURLOPT_USERAGENT => "spider", // who am i
        CURLOPT_AUTOREFERER => true, // set referer on redirect
        CURLOPT_CONNECTTIMEOUT => 5, // timeout on connect
        CURLOPT_TIMEOUT => 5, // timeout on response
        CURLOPT_MAXREDIRS => 10, // stop after 10 redirects
        
    );

    $ch = curl_init($url);
    curl_setopt_array($ch, $options);
    $content = curl_exec($ch);
    curl_close($ch);

    return $content;
}
function generateRandomString($length = 10, $protocol) {
    //include 'simple_html_dom.php';
    //$doc = str_get_html(get_web_page2("https://www.uuidgenerator.net/version1"));
    //return $message = $doc->find('#generated-uuid', 0)->text();
    return ($protocol == 'trojan') ? substr(md5(time()) , 5, 15) : guid();
}
function remove_inbound($server_id, $remark, $delete = 0) {
    global $telegram;
    $server_info = $telegram->db->query("SELECT * FROM server_info WHERE id=$server_id")->fetch(2);
    $panel_url = $server_info['panel_url'];
    $cookie = 'Cookie: session=' . $server_info['cookie'];

    $response = getList($server_id);
    if (!$response) return null;
    $response = $response->obj;
    foreach ($response as $row) {
        if ($row->remark == $remark) {
            $inbound_id = $row->id;
            $protocol = $row->protocol;
            $uniqid = ($protocol == 'trojan') ? json_decode($row->settings)->clients[0]->password : json_decode($row->settings)->clients[0]->id;
            $netType = json_decode($row->streamSettings)->network;
            $oldData = ['total' => $row->total, 'up' => $row->up, 'down' => $row->down, 'volume' => $row->total - $row->up - $row->down, 'port' => $row->port, 'protocol' => $protocol, 'expiryTime' => $row->expiryTime, 'uniqid' => $uniqid, 'netType' => $netType, 'security' => json_decode($row->streamSettings)->security, ];
            break;
        }
    }
    if ($delete == 1) {
        $url = "$panel_url/xui/inbound/del/$inbound_id";
        xuiCurl($url, $cookie);
    }

    //return $response = json_decode($response);
    //return $old_data;
    return $oldData;

}
// client
function remove_client($server_id, $inbound_id, $remark, $delete = 0) {
    global $telegram;
    $server_info = $telegram->db->query("SELECT * FROM server_info WHERE id=$server_id")->fetch(2);
    $panel_url = $server_info['panel_url'];
    $cookie = 'Cookie: session=' . $server_info['cookie'];

    $response = getList($server_id);
    if (!$response) return null;
    $response = $response->obj;
    $old_data = [];
    $oldclientstat = [];
    foreach ($response as $row) {
        if ($row->id == $inbound_id) {
            $settings = json_decode($row->settings);
            $clients = $settings->clients;
            foreach ($clients as $key => $client) {
                if ($client->email == $remark) {
                    $old_data = $client;
                    unset($clients[$key]);
                    break;
                }
            }

            $clientStats = is_null($row->clientStats) ? $row->clientInfo : $row->clientStats;
            foreach ($clientStats as $key => $clientStat) {
                if ($clientStat->email == $remark) {
                    $total = $clientStat->total;
                    $up = $clientStat->up;
                    $down = $clientStat->down;
                    //$clientStats[$key]->email = $new_remark;
                    //$clientStats[$key]->inboundId = $new_inbound_id;
                    break;
                }
            }
            break;
        }
    } //return $clientStats;
    //, 'clientStats' => $clientStats
    $settings->clients = array_values($clients);
    $settings = json_encode($settings);

    if ($delete == 1) {
        $dataArr = array(
            'up' => $row->up,
            'down' => $row->down,
            'total' => $row->total,
            'remark' => $row->remark,
            'enable' => 'true',
            'expiryTime' => $row->expiryTime, /*'clientStats' => $clientStats,*/
            'listen' => '',
            'port' => $row->port,
            'protocol' => $row->protocol,
            'settings' => $settings,
            'streamSettings' => $row->streamSettings,
            'sniffing' => $row->sniffing
        );
        // file_put_contents('fileee.txt',json_encode($dataArr));        die;
        $url = "$panel_url/xui/inbound/update/$inbound_id";
        xuiCurl($url, $cookie, $dataArr);
    }
    //return $response = json_decode($response);
    //return $old_data;
    return ['id' => $old_data->id, 'expiryTime' => $old_data->expiryTime, 'limitIp' => $old_data->limitIp, 'flow' => $old_data->flow, 'total' => $total, 'up' => $up, 'down' => $down, ];

}
function update_inbound_traffic($server_id, $remark, $volume, $days) {
    global $telegram;
    $server_info = $telegram->db->query("SELECT * FROM server_info WHERE id=$server_id")->fetch(2);
    $panel_url = $server_info['panel_url'];
    $cookie = 'Cookie: session=' . $server_info['cookie'];

    $response = getList($server_id);
    if (!$response) return null;
    $response = $response->obj;
    foreach ($response as $row) {
        if ($row->remark == $remark) {
            $inbound_id = $row->id;
            $total = $row->total;
            $up = $row->up;
            $down = $row->down;
            $port = $row->port;
            $netType = json_decode($row->streamSettings)->network;
            break;
        }
    }
    if ($days != 0) {
        $now_microdate = floor(microtime(true) * 1000);
        $extend_date = (864000 * $days * 100);
        $expire_microdate = ($now_microdate > $expiryTime) ? $now_microdate + $extend_date : $expiryTime + $extend_date;
    }

    if ($volume != 0) $total = $total - $up - $down + ($volume * 1073741824);

    $dataArr = array(
        'up' => 0,
        'down' => 0,
        'total' => is_null($total) ? $row->total : $total,
        'remark' => $row->remark,
        'enable' => 'true',
        'expiryTime' => is_null($expire_microdate) ? $row->expiryTime : $expire_microdate,
        'listen' => '',
        'port' => $row->port,
        'protocol' => $row->protocol,
        'settings' => $row->settings,
        'streamSettings' => $row->streamSettings,
        'sniffing' => $row->sniffing
    );

    $url = "$panel_url/xui/inbound/update/$inbound_id";
    return xuiCurl($url, $cookie, $dataArr);

}

function update_client_traffic($server_id, $inbound_id, $remark, $volume, $days) {
    global $telegram;
    $server_info = $telegram->db->query("SELECT * FROM server_info WHERE id=$server_id")->fetch(2);
    $panel_url = $server_info['panel_url'];
    $cookie = 'Cookie: session=' . $server_info['cookie'];

    $response = getList($server_id);
    if (!$response) return null;
    $response = $response->obj;
    $client_key = 0;
    foreach ($response as $row) {
        if ($row->id == $inbound_id) {
            $settings = json_decode($row->settings, true);
            $clients = $settings['clients'];
            foreach ($clients as $key => $client) {
                if ($client['email'] == $remark) {
                    $client_key = $key;
                    break;
                }
            }

            $clientStats = is_null($row->clientStats) ? $row->clientInfo : $row->clientStats;
            foreach ($clientStats as $key => $clientStat) {
                if ($clientStat->email == $remark) {
                    $total = $clientStat->total;
                    $up = $clientStat->up;
                    $down = $clientStat->down;
                    break;
                }
            }
            break;
        }
    }
    if ($volume != 0) {
        $client_total = $settings['clients'][$client_key]['totalGB'] - $up - $down;
        $extend_volume = $volume * 1073741824;
        $volume = ($client_total > 0) ? $client_total + $extend_volume : $extend_volume;
        resetClientTraffic($server_id, $remark);
        $settings['clients'][$client_key]['totalGB'] = $volume;
    }

    if ($days != 0) {
        $expiryTime = $settings['clients'][$client_key]['expiryTime'];
        $now_microdate = floor(microtime(true) * 1000);
        $extend_date = (864000 * $days * 100);
        $expire_microdate = ($now_microdate > $expiryTime) ? $now_microdate + $extend_date : $expiryTime + $extend_date;
        $settings['clients'][$client_key]['expiryTime'] = $expire_microdate;
    }

    $settings['clients'] = array_values($settings['clients']);
    $settings = json_encode($settings);
    //return $clients[$client_key];
    $dataArr = array(
        'up' => $row->up,
        'down' => $row->down,
        'total' => $row->total,
        'remark' => $row->remark,
        'enable' => 'true',
        'expiryTime' => $row->expiryTime,
        'listen' => '',
        'port' => $row->port,
        'protocol' => $row->protocol,
        'settings' => $settings,
        'streamSettings' => $row->streamSettings,
        'sniffing' => $row->sniffing
    );
    $url = "$panel_url/xui/inbound/update/$inbound_id";
    return xuiCurl($url, $cookie, $dataArr);
}

function resetClientTraffic($server_id, $remark) {
    global $telegram;
    $server_info = $telegram->db->query("SELECT * FROM server_info WHERE id=$server_id")->fetch(2);
    $panel_url = $server_info['panel_url'];
    $cookie = 'Cookie: session=' . $server_info['cookie'];
    $url = "$panel_url/xui/inbound/resetClientTraffic/$remark";
    return xuiCurl($url, $cookie);
}

function add_inbount_client($server_id, $client_id, $inbound_id, $expiryTime, $remark, $volume, $limitip = 1, $newarr = '') {
    global $telegram;
    $server_info = $telegram->db->query("SELECT * FROM server_info WHERE id=$server_id")->fetch(2);
    $panel_url = $server_info['panel_url'];
    $cookie = 'Cookie: session=' . $server_info['cookie'];

    $volume = ($volume == 0) ? 0 : $volume * 1073741824;

    $response = getList($server_id);
    if (!$response) return null;
    $response = $response->obj;
    foreach ($response as $row) {
        // $row = $row[0];
        if ($row->id == $inbound_id) {
            $iid = $row->id;
            $protocol = $row->protocol;
            break;
        }
    }
    if (!intval($iid)) return "inbound not Found";

    $settings = json_decode($row->settings, true);
    $id_label = $protocol == 'trojan' ? 'password' : 'id';
    if ($newarr == '') $settings['clients'][] = ["$id_label" => $client_id, "flow" => "xtls-rprx-direct", "email" => $remark, "limitIp" => $limitip, "totalGB" => $volume, "expiryTime" => $expiryTime];
    elseif (is_array($newarr)) $settings['clients'][] = $newarr;

    $settings['clients'] = array_values($settings['clients']);
    $settings = json_encode($settings);

    $dataArr = array(
        'up' => $row->up,
        'down' => $row->down,
        'total' => $row->total,
        'remark' => $row->remark,
        'enable' => 'true',
        'expiryTime' => $row->expiryTime,
        'listen' => '',
        'port' => $row->port,
        'protocol' => $row->protocol,
        'settings' => $settings,
        'streamSettings' => $row->streamSettings,
        'sniffing' => $row->sniffing
    );
    $url = "$panel_url/xui/inbound/update/$iid";
    return xuiCurl($url, $cookie, $dataArr);

}
// end client
function get_newheaders($netType, $request_header, $response_header, $type) {
    $input = explode(':', $request_header);
    $key = $input[0];
    $value = $input[1];

    $input = explode(':', $response_header);
    $reskey = $input[0];
    $resvalue = $input[1];

    $headers = '';
    if ($netType == 'tcp') {
        if ($type == 'none') {
            $headers = '{
              "type": "none"
            }';
        }
        else {
            $headers = '{
              "type": "http",
              "request": {
                "method": "GET",
                "path": [
                  "/"
                ],
                "headers": {
                   "' . $key . '": [
                     "' . $value . '"
                  ]
                }
              },
              "response": {
                "version": "1.1",
                "status": "200",
                "reason": "OK",
                "headers": {
                   "' . $reskey . '": [
                     "' . $resvalue . '"
                  ]
                }
              }
            }';
        }

    }
    elseif ($netType == 'ws') {
        if ($type == 'none') {
            $headers = '{}';
        }
        else {
            $headers = '{
              "' . $key . '": "' . $value . '"
            }';
        }
    }
    return $headers;

}

function add_inbound($server_id, $client_id, $protocol, $port, $expiryTime, $remark, $volume, $netType, $security = 'none') {
    global $telegram;
    $server_info = $telegram->db->query("SELECT * FROM server_info WHERE id=$server_id")->fetch(2);
    $panel_url = $server_info['panel_url'];
    $security = $server_info['security'];
    $tlsSettings = $server_info['tlsSettings'];
    $header_type = $server_info['header_type'];
    $request_header = $server_info['request_header'];
    $response_header = $server_info['response_header'];
    $cookie = 'Cookie: session=' . $server_info['cookie'];

    $volume = ($volume == 0) ? 0 : $volume * 1073741824;
    $headers = get_newheaders($netType, $request_header, $response_header, $header_type);

    //---------------------------------------Trojan------------------------------------//
    if ($protocol == 'trojan') {
        // protocol trojan
        if ($security == 'none') {
            $streamSettings = '{
    	  "network": "tcp",
    	  "security": "none",
    	  "tcpSettings": {
    		"header": {
			  "type": "none"
			}
    	  }
    	}';
            $settings = '{
    	  "clients": [
    		{
    		  "id": "' . $client_id . '",
    		  "flow": "xtls-rprx-direct"
    		}
    	  ],
    	  "decryption": "none",
    	  "fallbacks": []
    	}';
        }
        else {
            $streamSettings = '{
		  "network": "tcp",
		  "security": "' . $security . '",
		  "' . $security . 'Settings": ' . $tlsSettings . ',
		  "tcpSettings": {
			"header": {
			  "type": "none"
			}
		  }
		}';
            $settings = '{
		  "clients": [
			{
			  "password": "' . $client_id . '",
			  "flow": "xtls-rprx-direct"
			}
		  ],
		  "fallbacks": []
		}';
        }

        // trojan
        $dataArr = array(
            'up' => '0',
            'down' => '0',
            'total' => $volume,
            'remark' => $remark,
            'enable' => 'true',
            'expiryTime' => $expiryTime,
            'listen' => '',
            'port' => $port,
            'protocol' => $protocol,
            'settings' => $settings,
            'streamSettings' => $streamSettings,
            'sniffing' => '{
      "enabled": true,
      "destOverride": [
        "http",
        "tls"
      ]
    }'
        );
    }
    else {
        //-------------------------------------- vmess vless -------------------------------//
        if ($security == 'tls') {
            $tcpSettings = '{
    	  "network": "tcp",
    	  "security": "' . $security . '",
    	  "tlsSettings": ' . $tlsSettings . ',
    	  "tcpSettings": {
            "header": ' . $headers . '
          }
    	}';
            $wsSettings = '{
          "network": "ws",
          "security": "' . $security . '",
    	  "tlsSettings": ' . $tlsSettings . ',
          "wsSettings": {
            "path": "/",
            "headers": ' . $headers . '
          }
        }';
            $settings = '{
          "clients": [
            {
              "id": "' . $client_id . '",
              "alterId": 0
            }
          ],
          "disableInsecureEncryption": false
        }';
        }
        else {
            $tcpSettings = '{
    	  "network": "tcp",
    	  "security": "none",
    	  "tcpSettings": {
    		"header": ' . $headers . '
    	  }
    	}';
            $wsSettings = '{
          "network": "ws",
          "security": "none",
          "wsSettings": {
            "path": "/",
            "headers": ' . $headers . '
          }
        }';
            $settings = '{
    	  "clients": [
    		{
    		  "id": "' . $client_id . '",
    		  "flow": "xtls-rprx-direct"
    		}
    	  ],
    	  "decryption": "none",
    	  "fallbacks": []
    	}';
        }

        if ($protocol == 'vless') {
            $settings = '{
			  "clients": [
				{
				  "id": "' . $client_id . '",
				  "flow": "xtls-rprx-direct"
				}
			  ],
			  "decryption": "none",
			  "fallbacks": []
			}';
        }

        $streamSettings = ($netType == 'tcp') ? $tcpSettings : $wsSettings;
        if ($netType == 'grpc') {
            if ($security == 'tls') {
                $streamSettings = '{
  "network": "grpc",
  "security": "tls",
  "tlsSettings": {
    "serverName": "",
    "certificates": [
      {
        "certificateFile": "/root/cert.crt",
        "keyFile": "/root/private.key"
      }
    ],
    "alpn": []
  },
  "grpcSettings": {
    "serviceName": ""
  }
}';
            }
            else {
                $streamSettings = '{
  "network": "grpc",
  "security": "none",
  "grpcSettings": {
    "serviceName": ""
  }
}';
            }
        }

        // vmess - vless
        $dataArr = array(
            'up' => '0',
            'down' => '0',
            'total' => $volume,
            'remark' => $remark,
            'enable' => 'true',
            'expiryTime' => $expiryTime,
            'listen' => '',
            'port' => $port,
            'protocol' => $protocol,
            'settings' => $settings,
            'streamSettings' => $streamSettings,
            'sniffing' => '{
	  "enabled": true,
	  "destOverride": [
		"http",
		"tls"
	  ]
	}'
        );
    }
    $url = "$panel_url/xui/inbound/add";
    return xuiCurl($url, $cookie, $dataArr);
}

function update_inbound($server_id, $uniqid, $remark, $protocol, $netType = 'tcp', $security = 'none') {
    global $telegram;
    $server_info = $telegram->db->query("SELECT * FROM server_info WHERE id=$server_id")->fetch(2);
    $panel_url = $server_info['panel_url'];
    $security = $server_info['security'];
    $tlsSettings = $server_info['tlsSettings'];
    $header_type = $server_info['header_type'];
    $request_header = $server_info['request_header'];
    $response_header = $server_info['response_header'];
    $cookie = 'Cookie: session=' . $server_info['cookie'];

    $response = getList($server_id);
    if (!$response) return null;
    $response = $response->obj;
    foreach ($response as $row) {
        // $row = $row[0];
        if ($row->remark == $remark) {
            $iid = $row->id;
            break;
        }
    }
    if (!intval($iid)) return;

    $headers = get_newheaders($netType, $request_header, $response_header, $header_type);

    //-------------------------------Trojan------------------------//
    if ($protocol == 'trojan') {
        // protocol trojan
        if ($security == 'none') {
            $streamSettings = '{
    	  "network": "tcp",
    	  "security": "none",
    	  "tcpSettings": {
    		"header": {
			  "type": "none"
			}
    	  }
    	}';
            $settings = '{
    	  "clients": [
    		{
    		  "id": "' . $uniqid . '",
    		  "flow": "xtls-rprx-direct"
    		}
    	  ],
    	  "decryption": "none",
    	  "fallbacks": []
    	}';
        }
        else {
            $streamSettings = '{
		  "network": "tcp",
		  "security": "' . $security . '",
		  "' . $security . 'Settings": ' . $tlsSettings . ',
		  "tcpSettings": {
			"header": {
			  "type": "none"
			}
		  }
		}';
            $settings = '{
		  "clients": [
			{
			  "password": "' . $uniqid . '",
			  "flow": "xtls-rprx-direct"
			}
		  ],
		  "fallbacks": []
		}';
        }

        // trojan
        $dataArr = array(
            'up' => $row->up,
            'down' => $row->down,
            'total' => $row->total,
            'remark' => $remark,
            'enable' => 'true',
            'expiryTime' => $row->expiryTime,
            'listen' => '',
            'port' => $row->port,
            'protocol' => $protocol,
            'settings' => $settings,
            'streamSettings' => $streamSettings,
            'sniffing' => $row->sniffing
        );
    }
    else {
        if ($security == 'tls') {
            $tcpSettings = '{
    	  "network": "tcp",
    	  "security": "' . $security . '",
    	  "tlsSettings": ' . $tlsSettings . ',
    	  "tcpSettings": {
            "header": ' . $headers . '
          }
    	}';
            $wsSettings = '{
          "network": "ws",
          "security": "' . $security . '",
    	  "tlsSettings": ' . $tlsSettings . ',
          "wsSettings": {
            "path": "/",
            "headers": ' . $headers . '
          }
        }';
            $settings = '{
          "clients": [
            {
              "id": "' . $uniqid . '",
              "alterId": 0
            }
          ],
          "decryption": "none",
    	  "fallbacks": []
        }';
        }
        else {
            $tcpSettings = '{
    	  "network": "tcp",
    	  "security": "none",
    	  "tcpSettings": {
    		"header": ' . $headers . '
    	  }
    	}';
            $wsSettings = '{
          "network": "ws",
          "security": "none",
          "wsSettings": {
            "path": "/",
            "headers": {}
          }
        }';
            $settings = '{
    	  "clients": [
    		{
    		  "id": "' . $uniqid . '",
    		  "flow": "xtls-rprx-direct"
    		}
    	  ],
    	  "decryption": "none",
    	  "fallbacks": []
    	}';
        }

        $streamSettings = ($netType == 'tcp') ? $tcpSettings : $wsSettings;

        $dataArr = array(
            'up' => $row->up,
            'down' => $row->down,
            'total' => $row->total,
            'remark' => $remark,
            'enable' => 'true',
            'expiryTime' => $row->expiryTime,
            'listen' => '',
            'port' => $row->port,
            'protocol' => $protocol,
            'settings' => $settings,
            'streamSettings' => $streamSettings,
            'sniffing' => $row->sniffing
        );
    }
    $url = "$panel_url/xui/inbound/update/$iid";
    return xuiCurl($url, $cookie, $dataArr);
}

function getList($server_id) {
    global $telegram;
    $server_info = $telegram->db->query("SELECT * FROM server_info WHERE id=$server_id")->fetch(2);
    $panel_url = $server_info['panel_url'];
    $cookie = 'Cookie: session=' . $server_info['cookie'];
    $url = "$panel_url/xui/inbound/list";
    return xuiCurl($url, $cookie);
}

function genLink($server_id, $uniqid, $protocol, $remark, $port, $netType, $inbound_id = 0) {
    global $telegram;
    $server_info = $telegram->db->query("SELECT * FROM server_info WHERE id=$server_id")->fetch(2);
    $panel_url = $server_info['panel_url'];
    $server_ip = $server_info['ip'];
    $sni = $server_info['sni'];
    $header_type = $server_info['header_type'];
    $request_header = $server_info['request_header'];
    $response_header = $server_info['response_header'];
    $cookie = 'Cookie: session=' . $server_info['cookie'];

    $panel_url = str_ireplace('http://', '', $panel_url);
    $panel_url = str_ireplace('https://', '', $panel_url);
    $panel_url = strtok($panel_url, ":");
    if ($server_ip == '') $server_ip = $panel_url;

    $response = getList($server_id)->obj;
    foreach ($response as $row) {
        if ($inbound_id == 0) {
            if ($row->remark == $remark) {
                $tlsStatus = json_decode($row->streamSettings)->security;
                $tlsSetting = json_decode($row->streamSettings)->tlsSettings;
                $netType = json_decode($row->streamSettings)->network;
                if ($header_type == 'http') {
                    $request_header = explode(':', $request_header);
                    $host = $request_header[1];
                }
                if ($netType == 'grpc') {
                    if ($tlsStatus == 'tls') {
                        $alpn = $tlsSetting->certificates->alpn;
                    } // {  "network": "grpc",  "security": "none",  "grpcSettings": {    "serviceName": "dasfgds"  }}
                    $serviceName = json_decode($row->streamSettings)->grpcSettings->serviceName;
                }
                if ($tlsStatus == 'tls') {
                    $serverName = $tlsSetting->serverName;
                }
                if ($netType == 'kcp') {
                    $kcpSettings = json_decode($row->streamSettings)->kcpSettings;
                    $kcpType = $kcpSettings->header->type;
                    $kcpSeed = $kcpSettings->seed;
                }

                break;
            }
        }
        else {
            if ($row->id == $inbound_id) {
                //$remark = $row->email;
                $port = $row->port;
                $tlsStatus = json_decode($row->streamSettings)->security;
                $tlsSetting = json_decode($row->streamSettings)->tlsSettings;
                $netType = json_decode($row->streamSettings)->network;
                if ($netType == 'tcp') {
                    $headerType = json_decode($row->streamSettings)->tcpSettings->header->type;
                    $path = json_decode($row->streamSettings)->tcpSettings->header->request->path[0];
                    $host = json_decode($row->streamSettings)->tcpSettings->header->request->headers->Host[0];
                }
                elseif ($netType == 'ws') {
                    $headerType = json_decode($row->streamSettings)->wsSettings->header->type;
                    $path = json_decode($row->streamSettings)->wsSettings->path;
                    $host = json_decode($row->streamSettings)->wsSettings->headers->Host;
                }
                elseif ($netType == 'grpc') {
                    if ($tlsStatus == 'tls') {
                        $alpn = $tlsSetting->alpn;
                    } // {  "network": "grpc",  "security": "none",  "grpcSettings": {    "serviceName": "dasfgds"  }}
                    $serviceName = json_decode($row->streamSettings)->grpcSettings->serviceName;
                }
                elseif ($netType == 'kcp') {
                    $kcpSettings = json_decode($row->streamSettings)->kcpSettings;
                    $kcpType = $kcpSettings->header->type;
                    $kcpSeed = $kcpSettings->seed;
                }
                if ($tlsStatus == 'tls') {
                    $serverName = $tlsSetting->serverName;
                }

                break;
            }
        }

    }
    $protocol = strtolower($protocol);
    $remark .= REMARKNAME;
    if ($inbound_id == 0) {
        if ($protocol == 'vless') {
            $psting = '';
            if ($header_type == 'http') $psting .= "&path=/&host=$host";
            else $psting .= '';
            if ($netType == 'tcp' and $header_type == 'http') $psting .= '&headerType=http';
            if (strlen($sni) > 1) $psting .= "&sni=$sni";
            $outputlink = "$protocol://$uniqid@$server_ip:$port?type=$netType&security=$tlsStatus{$psting}#$remark";

            if ($netType == 'grpc') {
                if ($tlsStatus == 'tls') { /*$serverName*/
                    $outputlink = "$protocol://$uniqid@$server_ip:$port?type=$netType&security=$tlsStatus&serviceName=$serviceName&sni=$serverName#$remark";
                }
                else {
                    $outputlink = "$protocol://$uniqid@$server_ip:$port?type=$netType&security=$tlsStatus&serviceName=$serviceName#$remark";
                }

            }
        }

        if ($protocol == 'trojan') {
            $psting = '';
            if ($header_type == 'http') $psting .= "&path=/&host=$host";
            if ($netType == 'tcp' and $header_type == 'http') $psting .= '&headerType=http';
            if (strlen($sni) > 1) $psting .= "&sni=$sni";
            if ($tlsStatus != 'none') $tlsStatus = 'tls';
            $outputlink = "$protocol://$uniqid@$server_ip:$port?security=$tlsStatus{$psting}#$remark";
        }
        elseif ($protocol == 'vmess') {
            $vmessArr = ["v" => "2", "ps" => $remark, "add" => $server_ip, "port" => $port, "id" => $uniqid, "aid" => 0, "net" => $netType, "type" => $kcpType ? $kcpType : "none", "host" => is_null($host) ? '' : $host, "path" => (is_null($path) and $path != '') ? '/' : (is_null($path) ? '' : $path) , "tls" => (is_null($tlsStatus)) ? 'none' : $tlsStatus];
            if ($header_type == 'http') {
                $vmessArr['path'] = "/";
                $vmessArr['type'] = $header_type;
                $vmessArr['host'] = $host;
            }
            if ($netType == 'grpc') {
                if (!is_null($alpn) and json_encode($alpn) != '[]' and $alpn != '') $vmessArr['alpn'] = $alpn;
                if (strlen($serviceName) > 1) $vmessArr['path'] = $serviceName;
                $vmessArr['type'] = 'gun';
                $vmessArr['scy'] = 'auto';
            }
            if ($netType == 'kcp') {
                $vmessArr['path'] = $kcpSeed ? $kcpSeed : $vmessArr['path'];
            }
            if (strlen($sni) > 1) $vmessArr['sni'] = $sni;
            $urldata = base64_encode(json_encode($vmessArr, JSON_UNESCAPED_SLASHES, JSON_PRETTY_PRINT));
            $outputlink = "vmess://$urldata";
        }
    }
    else {
        // multi account on 1 connection
        if ($protocol == 'vless') {
            $psting = '';
            if (strlen($sni) > 1) $psting = "&sni=$sni";
            if (strlen($host) > 1) $psting .= "&host=$host";
            if ($netType == 'tcp') {
                if ($netType == 'tcp' and $header_type == 'http') $psting .= '&headerType=http';
                $outputlink = "$protocol://$uniqid@$server_ip:$port?type=$netType&path=/&security=$tlsStatus{$psting}#$remark";
            }
            elseif ($netType == 'ws') $outputlink = "$protocol://$uniqid@$server_ip:$port?type=$netType&path=/&security=$tlsStatus{$psting}#$remark";
            elseif ($netType == 'kcp') $outputlink = "$protocol://$uniqid@$server_ip:$port?type=$netType&security=$tlsStatus&headerType=$kcpType&seed=$kcpSeed#$remark";
            elseif ($netType == 'grpc') {
                if ($tlsStatus == 'tls') { /*$serverName*/
                    $outputlink = "$protocol://$uniqid@$server_ip:$port?type=$netType&security=$tlsStatus&serviceName=$serviceName&sni=$serverName#$remark";
                }
                else {
                    $outputlink = "$protocol://$uniqid@$server_ip:$port?type=$netType&security=$tlsStatus&serviceName=$serviceName#$remark";
                }
            }
        }
        elseif ($protocol == 'trojan') {
            $psting = '';
            if ($header_type == 'http') $psting .= "&path=/&host=$host";
            if ($netType == 'tcp' and $header_type == 'http') $psting .= '&headerType=http';
            if (strlen($sni) > 1) $psting .= "&sni=$sni";
            if ($tlsStatus != 'none') $psting .= "&security=tls&flow=xtls-rprx-direct";
            if ($netType == 'grpc') $psting = "&serviceName=$serviceName";

            $outputlink = "$protocol://$uniqid@$server_ip:$port{$psting}#$remark";
        }
        elseif ($protocol == 'vmess') {
            $vmessArr = ["v" => "2", "ps" => $remark, "add" => $server_ip, "port" => $port, "id" => $uniqid, "aid" => 0, "net" => $netType, "type" => ($headerType) ? $headerType : ($kcpType ? $kcpType : "none") , "host" => is_null($host) ? '' : $host, "path" => (is_null($path) and $path != '') ? '/' : (is_null($path) ? '' : $path) , "tls" => (is_null($tlsStatus)) ? 'none' : $tlsStatus];
            if ($netType == 'grpc') {
                if (!is_null($alpn) and json_encode($alpn) != '[]' and $alpn != '') $vmessArr['alpn'] = $alpn;
                if (strlen($serviceName) > 1) $vmessArr['path'] = $serviceName;
                $vmessArr['type'] = 'gun';
                $vmessArr['scy'] = 'auto';
            }
            if ($netType == 'kcp') {
                $vmessArr['path'] = $kcpSeed ? $kcpSeed : $vmessArr['path'];
            }

            if (strlen($sni) > 1) $vmessArr['sni'] = $sni;
            $urldata = base64_encode(json_encode($vmessArr, JSON_UNESCAPED_SLASHES, JSON_PRETTY_PRINT));
            $outputlink = "vmess://$urldata";
        }
    }

    return $outputlink;
}

function xuiCurl($url, $cookie, $dataArr = array() , $method = 'POST') {
    $curl = curl_init();
    $phost = str_ireplace('https://', '', str_ireplace('http://', '', $url));
    if (empty($dataArr)) {
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT => 15, // timeout on connect
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => array(
                //'Host: '.$phost,
                'User-Agent:  Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:108.0) Gecko/20100101 Firefox/108.0',
                'Accept:  application/json, text/plain, */*',
                'Accept-Language:  en-US,en;q=0.5',
                'Accept-Encoding:  gzip, deflate',
                'X-Requested-With:  XMLHttpRequest',
                $cookie
            ) ,
        ));
    }
    else {
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT => 15, // timeout on connect
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $dataArr,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => array(
                //'Host: '.$phost,
                'User-Agent:  Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:108.0) Gecko/20100101 Firefox/108.0',
                'Accept:  application/json, text/plain, */*',
                'Accept-Language:  en-US,en;q=0.5',
                'Accept-Encoding:  gzip, deflate',
                'X-Requested-With:  XMLHttpRequest',
                $cookie
            ) ,
        ));
    }
    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response);
}
