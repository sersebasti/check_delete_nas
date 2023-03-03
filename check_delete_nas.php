<?php
ini_set('max_execution_time', 0);

define('ROOTPATH', __DIR__);

$report_filename = 'report_check_nas.txt'; 

$repositories = array('fal_repository_archive', 'minuetto_repository_archive',
'ntv_repository_archive', 'sbb_repository_archive','ssbav_repository_archive',
'ssbavc_repository_archive', 'ti_repository_archive');
$nas_repository_rootpath = '/mnt/array1';

$nas_servername = '127.0.0.1';
$nas_port = 2222;
//$nas_servername = '10.112.11.22';
//$nas_port = 22;

$anno = 2016;
$mese = 1;

//$azione = "traccia";
$azione = "elimina";

writelog('Start Check Delete NAS');

//Connessione al NAS
require __DIR__ . '/vendor/autoload.php';
use phpseclib3\Net\SFTP;

$sftp = new SFTP($nas_servername, $nas_port);
if (!$sftp->login('omc4dl', 'dlav500')){writelog(new \Exception('Login failed')); }
else{writelog("Effettuato il login sftp al server: " .  $nas_servername . " porta: " . $nas_port);}

if($azione == "traccia"){
    file_put_contents($report_filename, "");
    writelog("Verranno tracciate le cartelle con anno <= ".$anno." e mese <= ".$mese." nelle seguenti repository:");
}
if($azione == "elimina"){writelog("Verranno eliminate le cartelle con anno <= ".$anno." e mese <= ".$mese." nelle seguenti repository:");}
foreach($repositories as $repository){writelog($repository);}

foreach($repositories as $repository){

    writelog("Controllo della repository:" .$repository );

    $matricole =  $sftp->nlist($nas_repository_rootpath.'/'.$repository);
    foreach($matricole as $matricola){
        if($matricola != '.' && $matricola != '..'){
            //writelog("- Controllo della matricola:" .$matricola);
            $matricala_logs =  $sftp->nlist($nas_repository_rootpath.'/'.$repository.'/'.$matricola);
            foreach($matricala_logs as $cartella){

                if($cartella != '.' && $cartella != '..' ){
                    //writelog("-- Controllo della cartella:".$cartella);

                    if (intval(substr($cartella, 0, 4)) <= $anno  and intval(substr($cartella, 5, 2)) <= $mese){
                         
                        if($azione == "traccia"){
                            $str = $repository.";".$matricola.";".$cartella.PHP_EOL; 
                            file_put_contents($report_filename, $str, FILE_APPEND | LOCK_EX);
                        }
                        
                        if($azione == "elimina"){
                            $del_path = $nas_repository_rootpath.'/'.$repository.'/'.$matricola.'/'.$cartella;
                            if($sftp->delete($del_path)){writelog("Eliminata cartella: " . $del_path);}
                            else{writelog("Errore durante eliminazione cartella: " . $del_path);}
                        }

                    }
                }
            } 
        }  
    }

}

writelog('End Check Delete NAS');

function writelog($log_msg){
    
    //$nl = '<br>';
    $nl = PHP_EOL;
    
    if( stristr(PHP_OS, "WIN") ) {$sl = "\\";}
    else {$sl = "/";}

    $logs_folder_name = 'logs';
    $logs_file_name = 'logs_check_nas.log';
    $log_date_folder = ROOTPATH.$sl.$logs_folder_name.$sl.date("Y-m-d");
    
    if(!is_dir(ROOTPATH.$sl.$logs_folder_name)){mkdir(ROOTPATH.$sl.$logs_folder_name);}
    if(!is_dir($log_date_folder)){mkdir($log_date_folder);}
    $log_path = $log_date_folder.$sl.$logs_file_name;

    echo $log_msg.$nl;
    file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );
}

?>