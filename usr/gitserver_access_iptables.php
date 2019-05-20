<?php

function update_firewall(array $allowed_addresses)
{
	//The ports to open for the whitelisted addresses
	$service_ports = [ 8080, 2222 ];
	//input/output files
	$iptables_file = "/etc/iptables-rules";
	$new_iptables = "/tmp/iptables-rules-new";

	$fin = fopen($iptables_file, "r");
	$fout = fopen($new_iptables, "w");
	if ($fin && $fout)
	{
		while ($line = fgets($fin))
		{
			if (strpos($line, "BEGIN GITSERVER_ACCESS_AUTOGENERATED") > 0)
			{
				fwrite($fout, "# BEGIN GITSERVER_ACCESS_AUTOGENERATED\n");
				break;
			}
			fwrite($fout, trim($line)."\n");
		}
		//Whitelist all the addresses for the specified service ports
		foreach ($allowed_addresses as $addr)
		{
			foreach ($service_ports as $port)
			{
				$line = "-A INPUT -s $addr -p tcp -m conntrack --ctstate NEW -m limit --limit 60/s --limit-burst 20 -m tcp --dport $port -j ACCEPT";
				fwrite($fout, trim($line)."\n");
			}
		}
		//Move the fin file pointer past the old autogenerated lines
		while ($line = fgets($fin))
		{
			if (strpos($line, "END GITSERVER_ACCESS_AUTOGENERATED") > 0)
			{
				fwrite($fout, "# END GITSERVER_ACCESS_AUTOGENERATED\n");
				break;
			}
		}
		//Write all the rest of the lines
		while ($line = fgets($fin))
		{
			fwrite($fout, trim($line)."\n");
		}
		fclose($fin);
		fclose($fout);

		//Apply the changes
		system("mv $new_iptables $iptables_file");
		system("iptables-restore $iptables_file");
	}

}

?>
