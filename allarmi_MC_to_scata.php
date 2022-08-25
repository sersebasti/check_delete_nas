<?php
/* 
autore: Serio Sebastiani
data: 25-08-2022
*/

/* 
Lo script controlla se ci sono degli allarmi SSC FAL (sotto forma di file .json),
generati da un algoritmo classico su MDP e salvati in una cartella sul server di Scatà 
a cui si può accedere via sftp.
Quindi si interfaccia con il dB MySql di Scata e scrive i nuovi allarmi oppure 
accora i nuovi linking runs summary
*/

/*
NOTA: 
Il programma prevede che il server su cui è installato il codice sia:
- su rete FastWeb
- connesso via SSH (bitvise) con il server di Scata 
- il codice posizionato funziona su filesystem windws
$cwd = 'C:\\xampp\\htdocs\\FAL\\to_scata_mc', $sl = addslashes('\\')
con operazione pianificata windows getcwd() crea problemi.
*/

//$cwd = addslashes(getcwd());
$cwd = 'C:\\xampp\\htdocs\\FAL\\to_scata_mc';
$sl = addslashes('\\');

//Crea cartella con data odierna per il log e imposta il percorso delf ile di log
$log_date_folder = $cwd .$sl.'allarmi_mc_logs'.$sl.date("Y-m-d");
if(!is_dir($log_date_folder)){mkdir($log_date_folder);}
$log_path = $log_date_folder .$sl.'allarmi_MC_to_scata.log';

$log_msg = 'Start allarmi MC to scata';
echo $log_msg.'<br>';
file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );

/*
Apro connessione con il dB MySql sul server di Scatà ($write_db = 'Scata') 
oppure con dB in locale ($write_db = 'Local') per fare test su MySql in locale
se presente in locale un dB MySQl  con stessa struttura
*/
$write_db = 'Scata';
if($write_db == 'Scata'){
  $servername = '127.0.0.1:43306';
  $dBusername = 's.sebastiani';
  $dBpassword = 'sergio9912!';
  $dBname = 'omc4dl';

  $omc4dl_table_issues = 'omc4dl.table_issues';
  $omc4dl_table_issues_linking_runs_summary = 'omc4dl.table_issues_linking_runs_summary';

  $conn_scata_omc4dl = mysqli_connect($servername,$dBusername,$dBpassword, $dBname);
  if (!$conn_scata_omc4dl) {
    $log_msg = 'Connessione al dB Scata fallita: '.mysqli_connect_error();
    echo $log_msg.'<br>';
    file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );
    die("Connessione fallita: " . mysqli_connect_error());
  }
  else{
    $log_msg = 'Connesso al dB Scata: '. $servername;
    echo $log_msg.'<br>';
    file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );
  }
}
else if($write_db == 'Local'){
  //Credenziali per connettersi al dB locale
  $servername = 'localhost';
  $dBusername = 'root';
  $dBpassword = '';
  $dBname = 'fal';

  $omc4dl_table_issues = 'scata_table_issues';
  $omc4dl_table_issues_linking_runs_summary = 'scata_table_issues_linking_runs_summary';

  $conn_scata_omc4dl = mysqli_connect($servername,$dBusername,$dBpassword, $dBname);
  if (!$conn_scata_omc4dl) {
    $log_msg = 'Connessione al dB locale fallita: '.mysqli_connect_error();
    echo $log_msg.'<br>';
    file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );
    die("Connessione fallita: " . mysqli_connect_error());
  }
  else{
    $log_msg = 'Connesso al dB locale';
    echo $log_msg.'<br>';
    file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );
  }
}

//Mi connetto via sftp alla cartella sul server di Scata
require ($cwd.$sl.'vendor'.$sl.'autoload.php');
use hpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;
$rsa = PublicKeyLoader::load(file_get_contents($cwd.$sl.'nuova_chiave_privata.ppk'));

$sftp = new SFTP('omc4dl.it.alstom.com', 49300);
if (!$sftp->login('s.sebastiani', $rsa)) {
    $log_msg = 'Login Failed';
    echo $log_msg.'<br>';
    file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );
    exit($log_msg);
}

//Definisco i path sulla cartela sftp cui ho accesso ee che sono utilizzati
$alarms_path = 'Classico\alarms';
$alarms_path_processati = 'Classico\alarms_processati';
$alarms_linking_runs_path = 'Classico\alarms_linking_runs';
$alarms_linking_runs_path_processati = 'Classico\alarms_linking_runs_processati';


$alarms_filnames = $sftp->nlist($alarms_path);
if (($key = array_search(".", $alarms_filnames)) !== false) {unset($alarms_filnames[$key]);}
if (($key = array_search("..", $alarms_filnames)) !== false) {unset($alarms_filnames[$key]);}
$log_msg = 'Tovati numero: ' . count($alarms_filnames)  . ' nella cartella ' . $alarms_path;
echo $log_msg.'<br>';
file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );


foreach($alarms_filnames as $key => $alarm_filename){
    //Nota: '\\' è cosi perchè server di Scata è windows
    $alarm_arr = json_decode($sftp->get($alarms_path . '\\' .$alarm_filename));
    $alarm_linking_runs_arr = json_decode($sftp->get($alarms_linking_runs_path . '\\' .$alarm_filename));

    if( $alarm_arr && $alarm_linking_runs_arr) 
      {
      $log_msg = 'OK analizzo allarmi nel file: '. $alarm_filename;
      echo $log_msg.'<br>';
      file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );

      foreach ($alarm_arr as $key_alarm => $value_alarm) {

        $log_msg = "Inizio controllo allarme numero: ".($key_alarm +1).PHP_EOL.
        "Id allarme: ".$value_alarm->id.PHP_EOL.
        "Tipo allarme: ".$value_alarm->tipo_allarme.PHP_EOL.
        "Impianto segnalato: ".$value_alarm->matricola_treno_pi_precedente.PHP_EOL.
        "Dettagli: ".PHP_EOL.$value_alarm->dettagli;
        echo $log_msg.'<br>';
        file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );
        
        //Filtro i linking_runs associati al solo allarme analizzato qui ($value_alarm) allarme e li metto in un array
        $alarm_linking_runs_arr_filterd = filter_linking_runs($alarm_linking_runs_arr,$value_alarm->id);
        
        //Determino il primo id run per acquisire le altre info sull'impianto (treno) da dB di Scata
        $primo_id_run = $alarm_linking_runs_arr_filterd[0]->fkid_run;
        $log_msg = "Primo id run: " . $primo_id_run;

        $omc4dl_data_info = get_omc4dl_data_info($primo_id_run);
        echo $log_msg.'<br>';
        file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );

        //echo 'Numero arresti: '.count($alarm_linking_runs_arr_filterd).PHP_EOL
        $dato_ora_ultimo_arresto = $alarm_linking_runs_arr_filterd[count($alarm_linking_runs_arr_filterd)-1]->data_ora_frenatura;
        $log_msg = 'Data ora ultimo arresto: '.$dato_ora_ultimo_arresto;
        echo $log_msg.'<br>';
        file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );

        $sql_check_allarme = "SELECT `ID_ISSUE`, `STATE`, `CLOSURE_DATETIME` FROM ".$omc4dl_table_issues." WHERE `fkID_ALARM` = '".$value_alarm->tipo_allarme."'
        AND `MATRICOLA_TRENO` = '".$value_alarm->matricola_treno_pi_precedente."' ORDER BY `ID_ISSUE` DESC LIMIT 1";
        $result_check_allarme = mysqli_query($conn_scata_omc4dl, $sql_check_allarme);
        if ($result_check_allarme === false) {die("Errore query: " . $sql_check_allarme);}
        $num_rows_check_allarme = mysqli_num_rows($result_check_allarme);

        if($num_rows_check_allarme == 0){
          $log_msg = 'Creo un allarme perchè non ci è mai stato';
          echo $log_msg.'<br>';
          file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );
          //Inserisco allarmi nel dB locale con stesso formato dB SCATA

          $sqlin_scata = "INSERT INTO ".$omc4dl_table_issues." (`ID_ISSUE`, `fkID_ALARM`, `ID_REPOSITORY`,
          `ID_DATALOGGER`, `SIGLA_COMPLESSO`, `MATRICOLA_TRENO`, `VER_SW_OBU_ERTMS`, `VER_SW_OBU_SCMT`,
          `OPENING_DATETIME`, `OPENING_USER`, `CLOSURE_DATETIME`, `CLOSURE_USER`, `LAST_UPDATE_DATETIME`,
          `ASSIGNED_TO_USER`, `STATE`, `RESOLUTION`, `SEVERITY`, `DETAILS`, `ACTIONS_DONE`, `ACTIONS_TO_DO`,
          `NOTE`, `AFFECTED_RUNS`, `FIRST_AFFECTED_RUN`, `LAST_AFFECTED_RUN`, `DISTANCE_AFTER_FIRST`,
          `POWER_ONS_AFTER_FIRST`, `MISSIONS_AFTER_FIRST`, `DISTANCE_AFTER_LAST`, `POWER_ONS_AFTER_LAST`,
          `MISSIONS_AFTER_LAST`, `LAST_RUN`, `ALARM_MODE`, `ALARM_EXPRESSION`, `HISTORY_LAST_UPDATE_BY_USER`,
          `UNDER_OBSERVATION_DATETIME`, `DISTANCE_AFTER_UNDER_OBSERVATION`, `POWER_ONS_AFTER_UNDER_OBSERVATION`,
          `MISSIONS_AFTER_UNDER_OBSERVATION`, `IS_OBSERVATION_FINISHED`)
          VALUES (NULL, '".$value_alarm->tipo_allarme."', '".$omc4dl_data_info['id_repository']."', '".$omc4dl_data_info['id_datalogger']."',
          '".$omc4dl_data_info['sigla_complesso']."', '".$value_alarm->matricola_treno_pi_precedente."',
          '".$omc4dl_data_info['ertms_version']."', '".$omc4dl_data_info['scmt_version']."',CURRENT_TIMESTAMP(), 'FAL_METODO_CLASSICO', CURRENT_TIMESTAMP(), NULL,
          CURRENT_TIMESTAMP(), NULL, 'NEW', '', 'MEDIUM', '".$value_alarm->dettagli."', NULL, NULL, NULL,
          '0',CURRENT_TIMESTAMP(), CURRENT_TIMESTAMP(), NULL, NULL, NULL, NULL, NULL, NULL,
          CURRENT_TIMESTAMP(), 'UNKNOWN', NULL, NULL, CURRENT_TIMESTAMP(), NULL, NULL, NULL, '0')";


          $resultin_scata = mysqli_query($conn_scata_omc4dl, $sqlin_scata);
          if ($resultin_scata === false) {
            $log_msg = "Errore query: " . $sqlin_scata;
            file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );
            die($log_msg);
          }
          else{
            $id_issue = mysqli_insert_id($conn_scata_omc4dl);
            $log_msg = 'Creato nuovo allarme per la prima volta con tipo allarme:  '. $value_alarm->tipo_allarme .' e impianto: '. $value_alarm->matricola_treno_pi_precedente;
            file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );
            echo $log_msg.'<br>';
          }

        }
        else{
          $log_msg = 'Allarme era gia inserito - effettuo controllo';
          echo $log_msg.'<br>';
          file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );

          $arr_check_allarme = mysqli_fetch_all($result_check_allarme, MYSQLI_ASSOC);
          $last_allarm_id_issue = $arr_check_allarme[0]['ID_ISSUE'];
          $last_allarm_state = $arr_check_allarme[0]['STATE'];
          $last_allarm_closure_date = $arr_check_allarme[0]['CLOSURE_DATETIME'];

          if($last_allarm_state == 'CLOSED'){
            if($dato_ora_ultimo_arresto > $last_allarm_closure_date){

                $log_msg = 'Creo nuovo allarme: data ora ultimo arresto '.$dato_ora_ultimo_arresto.
                ' successiava a data ora ultima chiusura allarme '.$last_allarm_closure_date;
                echo $log_msg.'<br>';
                file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );

                $sqlin_scata = "INSERT INTO ".$omc4dl_table_issues." (`ID_ISSUE`, `fkID_ALARM`, `ID_REPOSITORY`,
                `ID_DATALOGGER`, `SIGLA_COMPLESSO`, `MATRICOLA_TRENO`, `VER_SW_OBU_ERTMS`, `VER_SW_OBU_SCMT`,
                `OPENING_DATETIME`, `OPENING_USER`, `CLOSURE_DATETIME`, `CLOSURE_USER`, `LAST_UPDATE_DATETIME`,
                `ASSIGNED_TO_USER`, `STATE`, `RESOLUTION`, `SEVERITY`, `DETAILS`, `ACTIONS_DONE`, `ACTIONS_TO_DO`,
                `NOTE`, `AFFECTED_RUNS`, `FIRST_AFFECTED_RUN`, `LAST_AFFECTED_RUN`, `DISTANCE_AFTER_FIRST`,
                `POWER_ONS_AFTER_FIRST`, `MISSIONS_AFTER_FIRST`, `DISTANCE_AFTER_LAST`, `POWER_ONS_AFTER_LAST`,
                `MISSIONS_AFTER_LAST`, `LAST_RUN`, `ALARM_MODE`, `ALARM_EXPRESSION`, `HISTORY_LAST_UPDATE_BY_USER`,
                `UNDER_OBSERVATION_DATETIME`, `DISTANCE_AFTER_UNDER_OBSERVATION`, `POWER_ONS_AFTER_UNDER_OBSERVATION`,
                `MISSIONS_AFTER_UNDER_OBSERVATION`, `IS_OBSERVATION_FINISHED`)
                VALUES (NULL, '".$value_alarm->tipo_allarme."', '".$omc4dl_data_info['id_repository']."', '".$omc4dl_data_info['id_datalogger']."',
                '".$omc4dl_data_info['sigla_complesso']."', '".$value_alarm->matricola_treno_pi_precedente."',
                '".$omc4dl_data_info['ertms_version']."', '".$omc4dl_data_info['scmt_version']."',CURRENT_TIMESTAMP(), 'FAL_METODO_CLASSICO', CURRENT_TIMESTAMP(), NULL,
                CURRENT_TIMESTAMP(), NULL, 'NEW', '', 'MEDIUM', '".$value_alarm->dettagli."', NULL, NULL, NULL,
                '0',CURRENT_TIMESTAMP(), CURRENT_TIMESTAMP(), NULL, NULL, NULL, NULL, NULL, NULL,
                CURRENT_TIMESTAMP(), 'UNKNOWN', NULL, NULL, CURRENT_TIMESTAMP(), NULL, NULL, NULL, '0')";


                $resultin_scata = mysqli_query($conn_scata_omc4dl, $sqlin_scata);
                if ($resultin_scata === false) {
                  $log_msg = "Errore query: " . $sqlin_scata;
                  file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );
                  die($log_msg);
                }
                else{$id_issue = mysqli_insert_id($conn_scata_omc4dl);}
                  $log_msg = 'Creato nuovo allarme con tipo allarme:  '. $value_alarm->tipo_allarme .
                  ' e impianto: '. $value_alarm->matricola_treno_pi_precedente;
                  file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );
                  echo $log_msg.'<br>';

                  //file_put_contents($log_path, date("Y-m-d H:i:s") . ': Creato nuovo allarme perchè ultimo chuiso prima di nuovo arresto
                  //con tipo allarme:  '. $value_alarm->tipo_allarme .' e impianto: '. $value_alarm->matricola_treno_pi_precedente .PHP_EOL,FILE_APPEND );

            }
            else{
              $log_msg = 'Non creo nuovo allarme e non accodo gli arresti ad allarme esistente. Data ora ultimo arresto '.$dato_ora_ultimo_arresto.
              ' precedente a data ora ultima chiusura allarme '.$last_allarm_closure_date;
                file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );
                echo $log_msg.'<br>';
                $id_issue = -1;
            }
          }
          else{
            $log_msg = 'Eventualmente accoda ulteriori arresti a ultimo allarme non chiuso: '. $last_allarm_id_issue;
            file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );
            echo $log_msg.'<br>';
            $id_issue = $last_allarm_id_issue;
          }
        }

        // $id_issue è -1 se allarme già presente e ultimo arresto precedente data chiusura 
        if($id_issue != -1){

          $log_msg = 'Controllo se eventualmente accodare nuovi id_run a allarme: '. $id_issue;
          file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );
          echo $log_msg.'<br>';

          //Determino il massinmo id_run in modo da inserire solo i successivi
          $sql_max_run = "SELECT max(`fkID_RUN`) AS `MAX_fkID_RUN` FROM ".$omc4dl_table_issues_linking_runs_summary." WHERE `fkID_ISSUE` = '".$id_issue."'";
          $result_max_run = mysqli_query($conn_scata_omc4dl, $sql_max_run);
          if ($result_max_run === false) {die("Errore query: " . $sql_max_run);}
          $arr_max_run = mysqli_fetch_all($result_max_run, MYSQLI_ASSOC);
          $max_id_run = $arr_max_run[0]['MAX_fkID_RUN'];
          //echo 'max_id_run: '.$max_id_run.PHP_EOL;

          foreach ($alarm_linking_runs_arr_filterd as $key => $value) {
            $id_run = $value->fkid_run;
            //Accoda nuovi eventi sole se è un nuovo id_run
            if($id_run > $max_id_run){
              $ts = $value->ts_at;
              $distanza = $value->distance_at;
              $velocita = $value->speed_at;

              $sql_runs = "INSERT INTO ".$omc4dl_table_issues_linking_runs_summary." (`ID`, `CATEGORY`,
              `TS_AT`, `DISTANCE_AT`, `SPEED_AT`, `fkID_ISSUE`, `fkID_RUN`)
              VALUES (NULL, 'IN_MISSION', '".$ts."', '".$distanza."', '".$velocita."', '".$id_issue."', '".$id_run."')";
              $result_runs = mysqli_query($conn_scata_omc4dl, $sql_runs);
              if ($result_runs === false) {
                $log_msg = "Errore query: " . $sql_runs;
                file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );
                die($log_msg);
              }
              else{
                $log_msg =  'Accodato id_run: '.$id_run;
                file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );
                echo $log_msg.'<br>';
              }

            }
            else{
              $log_msg =  'Non accodato id_run: '.$id_run. ' perchè già presente nel dB';
              file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );
              echo $log_msg.'<br>';
            }
          }
        }
        else{
          $log_msg = 'Non accodo id_run';
          file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );
          echo $log_msg.'<br>';
        }
      }
    
    //sposta i files analizzati
    $sftp->rename($alarms_path . '\\' .$alarm_filename, $alarms_path_processati . '\\' .$alarm_filename);
    $log_msg = "Spostato file: " .$alarm_filename . ' in ' . $alarms_path_processati;
    file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );
    echo $log_msg.'<br>';
    
    $sftp->rename($alarms_linking_runs_path . '\\' .$alarm_filename, $alarms_linking_runs_path_processati . '\\' .$alarm_filename);
    $log_msg =  "Spostato file" .$alarm_filename. ' in ' . $alarms_linking_runs_path_processati;
    file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );
    echo $log_msg.'<br>';
   
    }//condizione di corretta acquisiszione files allarmi e elinking runs associati
    else{
      $log_msg = 'Errore nel acquisire allarme: ' . $alarm_filename;
      echo $log_msg.'<br>';
      file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );
    } 
}//ciclo for sui filea allarmi

mysqli_close($conn_scata_omc4dl);
if($write_db == 'Scata'){
  $log_msg = "Disconnesso dal dB Scata omc4dl";
  file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );
  echo $log_msg.'<br>';
}
else if($write_db == 'Local'){
  $log_msg = "Disconnesso dal dB Local fal";
  file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );
  echo $log_msg.'<br>';
}

$log_msg = 'Stop allarmi MC to scata';
file_put_contents($log_path, date("Y-m-d H:i:s") . ': '.$log_msg.PHP_EOL,FILE_APPEND );
echo $log_msg.'<br>';




function filter_linking_runs($alarm_linking_runs_arr, $alarm_id){
  $R = [];
  foreach ($alarm_linking_runs_arr as $key => $value) {
    if($value->fkid_issue == $alarm_id){$R[]=$value;}
  }
  return $R;
}

function get_omc4dl_data_info($primo_id_run){

  echo 'Acquisisco info da omc4dl_data per id_run: ' . $primo_id_run .PHP_EOL;

  $servername = '127.0.0.1:43306';
  $dBusername = 's.sebastiani';
  $dBpassword = 'sergio9912!';
  $dBname = 'omc4dl_data'; //dB in cui sono dati configuraione
  $conn_scata_omc4dl_data = mysqli_connect($servername,$dBusername,$dBpassword, $dBname);
  if (!$conn_scata_omc4dl_data) {die("Connessione fallita: " . mysqli_connect_error());}
  else{echo 'Connesso al dB Scata_data'.PHP_EOL;}

  $sql_runs = "SELECT * FROM `omc4dl_data`.`table_runs_summary` WHERE `ID` = ". $primo_id_run;
  $result_runs = mysqli_query($conn_scata_omc4dl_data, $sql_runs);
  if ($result_runs === false) {die("Errore query selezione * da con: " . $sql_runs);}

  $arr_runs = mysqli_fetch_all($result_runs, MYSQLI_ASSOC);

  $R = [];

  $R['id_repository'] = $arr_runs[0]['ID_REPOSITORY'];
  $R['id_datalogger'] = $arr_runs[0]['ID_DATALOGGER'];
  $R['id_scmt_config'] = $arr_runs[0]['fkID_SCMT_CONFIG'];
  $R['ertms_version'] = $arr_runs[0]['fkVERSIONE_SSB_ERTMS'];


  $sql_scmt_conf = "SELECT * FROM `omc4dl_data`.`table_scmt_config` WHERE `ID` = ". $R['id_scmt_config'];
  $result_scmt_conf = mysqli_query($conn_scata_omc4dl_data, $sql_scmt_conf);
  if ($result_scmt_conf === false) {die("Errore query selezione * da con: " . $sql_scmt_conf);}
  $arr_scmt_conf = mysqli_fetch_all($result_scmt_conf, MYSQLI_ASSOC);

  $R['scmt_version'] = $arr_scmt_conf[0]['fkVERSIONE_SSB_SCMT'];
  $R['sigla_complesso'] = $arr_scmt_conf[0]['SIGLA_COMPLESSO'];

  mysqli_close($conn_scata_omc4dl_data);
  echo "Disconnesso dal dB Scata omc4dl_data".PHP_EOL;

  //print_r($R);
  return $R;
}

?>
