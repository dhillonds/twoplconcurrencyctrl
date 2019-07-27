<?php
require 'dbconn.php';

$c_filename = "input7.txt";
$current_file = fopen($c_filename, "r") or die("Unable to open file");
define('ACTIVE', 1);
define('BLOCKED', 2);
define('COMMITTED', 3);
define('ABORTED', -1);

define('READ_LOCK', 1);
define('WRITE_LOCK', 2);

//$log_data = "Opened file named '".$c_filename."'\n";
echo "Opened file named '".$c_filename."<br>";
$i=1;
$c_timestamp = 1;
while(!feof($current_file)) {
	$c_line = str_split(fgets($current_file));
	$cur_action = $c_line[0];
	$cur_tid = $c_line[1];
	if($cur_action == 'b'){
		insert_transaction($cur_tid, $c_timestamp, $conn);
		$c_timestamp++;
	}else if($cur_action == 'r'){
		$t_data = get_transaction($cur_tid, $conn);
		if(!empty($t_data) && is_array($t_data)){
			if($c_line[2] == " " && $c_line[3] == "("){
				$req_item = "'".$c_line[4]."'";
			}else if($c_line[2] == "("){
				$req_item = "'".$c_line[3]."'";
			}			
			if($t_data['trans_state'] == ABORTED){
				echo "This transaction is already aborted, so no operations can be handled for it.<br>";
			}else if($t_data['trans_state'] == COMMITTED){
				echo "This transaction has been committed already <br>";
			}else{
				$lock_details = get_item_lock($req_item, $conn);
				if(!empty($lock_details) && is_array($lock_details)){
					if($lock_details['lock_state'] == READ_LOCK){
						$new_tids = "'".$lock_details['tids'].",".$cur_tid."'";
						remove_lock($req_item, $conn);
						insert_lock($req_item, READ_LOCK, $new_tids, $conn, $lock_details['w_tids_r'], $lock_details['w_tid_w']);
					}else if($lock_details['lock_state'] == WRITE_LOCK){
						$old_trans = get_transaction($lock_details['tids'], $conn);
						if($old_trans['t_timestamp'] == $t_data['t_timestamp']){
							if(!empty($lock_details['w_tids_r'])){
								$new_tids = "'".$cur_tid.",".$lock_details['w_tids_r']."'";
							}else{
								$new_tids = "'".$cur_tid."'";
							}
							remove_lock($req_item, $conn);
							insert_lock($req_item, READ_LOCK, $new_tids, $conn);						
						}else if($old_trans['t_timestamp'] < $t_data['t_timestamp']){
							if(!empty($lock_details['w_tids_r'])){
								$new_waiting_tids = "'".$cur_tid.",".$lock_details['w_tids_r']."'";
							}else{
								$new_waiting_tids = "'".$cur_tid."'";
							}
							remove_lock($req_item, $conn);
							insert_lock($req_item, READ_LOCK, $lock_details['tids'], $conn, $new_waiting_tids);
							update_transaction($cur_tid, BLOCKED, $conn, 'blocked');
						}else if($old_trans['t_timestamp'] > $t_data['t_timestamp']){
							if(!empty($lock_details['w_tids_r'])){
								$new_tids = "'".$cur_tid.",".$lock_details['w_tids_r']."'";
							}else{
								$new_tids = "'".$cur_tid."'";
							}
							remove_lock($req_item, $conn);
							update_transaction($lock_details['tid'], ABORTED, $conn, 'aborted');
							insert_lock($req_item, READ_LOCK, $new_tids, $conn);
							update_transaction($cur_tid, ACTIVE, $conn, 'active');
						}
					}
				}else{
					insert_lock($req_item, READ_LOCK, $cur_tid, $conn);
				}
			}
			//echo "it is read for tid ".$cur_tid." on item ".$req_item."<br>";
		}else{
			die("Something went wrong");
		}
	}else if($cur_action == 'w'){
		$t_data = get_transaction($cur_tid, $conn);
		if(!empty($t_data) && is_array($t_data)){
			if($c_line[2] == " " && $c_line[3] == "("){
				$req_item = "'".$c_line[4]."'";
			}else if($c_line[2] == "("){
				$req_item = "'".$c_line[3]."'";
			}
			if($t_data['trans_state'] == ABORTED){
				echo "This transaction is already aborted, so no operations can be handled for it.<br>";
			}else if($t_data['trans_state'] == COMMITTED){
				echo "This transaction has been committed already <br>";
			}else{
				$lock_details = get_item_lock($req_item, $conn);
				if(!empty($lock_details) && is_array($lock_details)){
					if($lock_details['lock_state'] == READ_LOCK){
						remove_lock($req_item, $conn);
						insert_lock($req_item, WRITE_LOCK, $cur_tid, $conn);
					}else if($lock_details['lock_state'] == WRITE_LOCK){
						$old_trans = get_transaction($lock_details['tids'], $conn);
						//Case of same transaction
						if($old_trans['t_timestamp'] < $t_data['t_timestamp']){
							if(!empty($lock_details['w_tid_w'])){
								update_transaction($lock_details['w_tid_w'], ABORTED, $conn, 'aborted');
							}
							remove_lock($req_item, $conn);
							insert_lock($req_item, WRITE_LOCK, $lock_details['tids'], $conn, NULL, $cur_tid);
							update_transaction($cur_tid, BLOCKED, $conn, 'blocked');
						}else if($old_trans['t_timestamp'] > $t_data['t_timestamp']){
							if(!empty($lock_details['w_tid_w'])){
								$waiting_w_tid = get_transaction($lock_details['w_tid_w'], $conn);
								if($waiting_w_tid['t_timestamp'] < $t_data['t_timestamp']){
									$new_tid = "'".$lock_details['w_tid_w']."'";
									$new_waiting_tid = $cur_tid;
									update_transaction($cur_tid, BLOCKED, $conn, 'blocked');
								}else{
									$new_tid = $cur_tid;
									$new_waiting_tid = NULL;
									update_transaction($lock_details['w_tid_w'], ABORTED, $conn, 'aborted');
								}
							}else{
								$new_tid = $cur_tid;
								$new_waiting_tid = NULL;
							}
							remove_lock($req_item, $conn);
							update_transaction($lock_details['tid'], ABORTED, $conn, 'aborted');
							insert_lock($req_item, WRITE_LOCK, $new_tid, $conn, NULL, $new_waiting_tid);
							update_transaction($new_tid, ACTIVE, $conn, 'active');
						}
					}
				}else{
					insert_lock($req_item, WRITE_LOCK, $cur_tid, $conn);
				}
			}
			//echo "it is write for tid ".$cur_tid." on item ".$req_item."<br>";
		}else{
			die("Something went wrong <br>");
		}
	}else if($cur_action == 'e'){
		$t_data = get_transaction($cur_tid, $conn);
		if($t_data['trans_state'] == ACTIVE){
			remove_lock(NULL, $conn,"'".$cur_tid."'");
			update_transaction("'".$cur_tid."'", COMMITTED, $conn, 'committed');
		}else{
			remove_lock(NULL, $conn,"'".$cur_tid."'");
			update_transaction("'".$cur_tid."'", ABORTED, $conn, 'committed');
		}
	}
	//$log_data .= "Closed file.\n";
	$i++;
}
fclose($current_file);
// $log_data .= "Closed file.\n";
// $logfile = "log_".(substr($c_filename, 0, (strpos($c_filename, ".")))).".txt";
// $open_log = fopen($logfile, "w") or die("Unable to open file");
// fwrite($open_log, $log_data);
// fclose($open_log);
function insert_transaction($tid = NULL, $t_timestamp = NULL, $connection = array()){
	if(empty($tid) || empty($t_timestamp) || empty($connection)){
		echo "Something went wrong <br>";
		return FALSE;
	}
	$isql = "INSERT INTO trans_table (tid, trans_state, t_timestamp) VALUES ($tid, ".ACTIVE.", $t_timestamp);";
	if($connection->query($isql) === TRUE){
		echo "Transaction with tid->".$tid." is inserted in Transaction table with state 'active'<br>";
	}else{
		echo $connection->error;
	}
}
function update_transaction($tid = NULL, $trans_state = NULL, $connection = array(), $state_name = ' '){
	if(empty($tid) || empty($trans_state) || empty($connection)){
		echo "Something went wrong <br>";
		return FALSE;
	}
	$isql = "UPDATE trans_table SET trans_state = $trans_state WHERE tid = $tid";
	if($connection->query($isql) === TRUE){
		echo "Transaction with tid->".$tid." in Transaction table updated with state '".$state_name."'<br>";
	}else{
		echo $connection->error;
	}
}
function get_transaction($tid = NULL, $connection = array()){
	if(empty($tid) || empty($connection)){
		echo "Something went wrong.<br>";
		return FALSE;
	}
	$isql = "SELECT tid, trans_state, t_timestamp FROM trans_table WHERE tid = $tid";
	$result = $connection->query($isql);
	if ($result->num_rows > 0) {
		$row = $result->fetch_assoc();
		return $row;
	} else {
	    return FALSE;
	}
}
function get_item_lock($item_name = NULL, $connection = array()){
	if(empty($item_name) || empty($connection)){
		echo "Something went wrong.<br>";
		return FALSE;
	}
	$isql = "SELECT lock_item, lock_state, tids, w_tids_r, w_tid_w FROM lock_table WHERE lock_item = $item_name ";
	$result = $connection->query($isql);
	if ($result->num_rows > 0) {
		$row = $result->fetch_assoc();
		return $row;
	} else {
	    return FALSE;
	}
}
function remove_lock($item_name = NULL, $connection = array(), $locking_tid = NULL){
	if(empty($connection)){
		echo "Something went wrong.<br>";
		return FALSE;	
	}
	if(empty($item_name)){
		if(!empty($locking_tid)){
			$isql2 = "DELETE FROM lock_table WHERE tids = $locking_tid ";
			if($connection->query($isql2) === TRUE){
				echo "Lock on item '".$lock_item."' by item_name in Lock table has been removed<br>";
			}else{
				echo $connection->error;
			}
			return TRUE;
		}else{
			echo "Something went wrong <br>";
			return FALSE;		
		}
	}
	$isql = "DELETE FROM lock_table WHERE lock_item = $item_name ";
	if($connection->query($isql) === TRUE){
		echo "Lock on item '".$lock_item."' in Lock table has been removed<br>";
	}else{
		echo $connection->error;
	}
}
function insert_lock($lock_item = NULL, $lock_state = NULL, $tids = NULL, $connection = array(), $w_tids_r = NULL, $w_tid_w = NULL){
	if(empty($lock_item) || empty($lock_state) || empty($tids) || empty($connection)){
		echo "Something went wrong.<br>";
		return FALSE;
	}
	$isql = "INSERT INTO lock_table (lock_item, lock_state, tids) VALUES ($lock_item, $lock_state, $tids)";
	if($connection->query($isql) === TRUE){
		if($lock_state == READ_LOCK){
			echo "A shared lock on item '".$lock_item."' is granted for transaction(s) with tid(s)->".$tids."<br>";
		}else if($lock_state == WRITE_LOCK){
			echo "An exclusive lock on item '".$lock_item."' is granted for transaction with tid->".$tids."<br>";
		}

		if(!empty($w_tids_r)){
			$isql2 = "UPDATE lock_table SET w_tids_r = $w_tids_r WHERE lock_item = $lock_item ";
			if($connection->query($isql2) === TRUE){
				echo "The transaction(s) with tid(s)->".$tids." are waiting to gain the shared lock<br>";
			}
		}
		if(!empty($w_tid_w)){
			$isql2 = "UPDATE lock_table SET w_tid_w = $w_tid_w WHERE lock_item = $lock_item ";
			if($connection->query($isql2) === TRUE){
				echo "The transaction with tid->".$tids." is waiting to gain the exclusive lock<br>";
			}
		}
	}else{
		echo $connection->error;
	}
}