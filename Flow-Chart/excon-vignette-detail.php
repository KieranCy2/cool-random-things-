<?php
require_once('../../exmato/includes/connections/exmato.php');
require_once('../exmanews/connections/exmanews.php');
require_once('../includes/connections/exercise.php');
require_once('../includes/authorization.php');
require_once('../includes/messages.php');
require_once('../includes/crypt.php');
forceAuthorisation("Exmaroot,Exercise Admin,Excon");
?>
<?php
$colname_user_profile = "-1";
if (isset($_SESSION['MM_Username'])) {
  $colname_user_profile = cy2_crypt($_SESSION['MM_Username'],'d');
}
$colname_vignette = "-1";
if (isset($_GET['vignette_id'])) {
  $colname_vignette = cy2_crypt($_GET['vignette_id'],'d');
}
else {
     header("location:javascript://history.go(-1)");
}


mysqli_select_db($exmato, $database_exmato);
// Use exercise's DB name to lookup exercise by slug in exmato exercise table, as they are the same
$exercisedetailQuery = sprintf('SELECT exercise_id, exercise_name, exercise_type, slug FROM exercise WHERE slug = %s', GetSQLValueString($exmato, $database_exercise, "text"));
$exercisedetailResult = mysqli_query($exmato, $exercisedetailQuery) or die (mysqli_error($exmato));
$exercisedetail = mysqli_fetch_assoc($exercisedetailResult);
$exercise_name = $exercisedetail['slug'];

function fnProcess_IFile($aFile)
{
     $maxsize = 15728640;

     // Define file upload path
     $sFile_Path = "master_item_list_docs";

     // If no files uploaded return to caller
     if(empty($aFile))
          return;

     // Convert associative file array to numerically indexed
     $aFile = array_values($aFile);

     // If file size is empty return to caller
     if(empty($aFile[0]['size']))
          return;

    // allowed files types
     $allowedExts = array(
     "pdf",
     "doc",
     "docx",
     "txt",
     "ppt",
     "pptx",
     "xls",
     "xlsx",
     "jpg",
     "jpeg",
     "JPG",
     "JPEG",
     "png",
     "PNG",
     "pcap",
     "csv",
     );

     $extension = end(explode(".", $aFile[0]['name']));

     //checking if file to be uploaded extension is in allowed list
    if ( ! ( in_array($extension, $allowedExts ) ) ) {
          addMessage('<font color="red">Error Uploading File. File Extension Is Not Allowed. The Following File Extensions Are Allowed: pdf, doc, docx, txt, ppt, pptx, xls, xlsx, jpg, jpeg, png</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
    }

    //Check File size
    if($aFile[0]['size'] >= $maxsize) {
         addMessage('<font color="red">File Size Too Big</font>');
         header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
         exit();
    }

    // Define file name
    if(!empty($_POST['afilename']))
    {
         // Get file extension
         global $oFile;
         $_POST['afilename'] = str_replace(' ', "_", $_POST['afilename']);
         $file = basename($_POST['afilename'],".".$extension);
         $asFile = sprintf("%s/%s", $sFile_Path, $file.'.'.$extension);
         $oFile = sprintf($file.'.'.$extension);
    }

     // If the file already exists, bomb out
     if(file_exists($asFile)) {
          addMessage('<font color="red">File Already Exists. Please Rename The File And Upload Again</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }

     // If the file can't be moved to it's location, bomb out
     if(!move_uploaded_file($aFile[0]['tmp_name'], $asFile)) {
          addMessage('<font color="red">Upload Failed. Unable To Move The Item</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }

     // If file permissions can't be changed, bomb out
     if(!chmod($asFile, 0444)) {
          addMessage('<font color="red">Upload Failed. Failed To Change Permission On Item</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }

     $allowedMimeTypes = array(
     "application/pdf",
     "application/msword",
     "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
     "text/csv",
     "text/plain",
     "application/vnd.ms-powerpoint",
     "application/vnd.openxmlformats-officedocument.presentationml.presentation",
     "application/vnd.ms-excel",
     "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
     "application/vnd.tcpdump.pcap",
     "image/jpeg",
     "image/png"
     );

     $mime = sprintf(mime_content_type($asFile));

     //checking if file to be uploaded mime type is in allowed list
     if (!in_array($mime, $allowedMimeTypes,TRUE)) {
          echo shell_exec("rm -rf $asFile");
          addMessage("<font color='red'>Error Uploading Item File. The Following MIME Type: '$mime' Is Not Allowed</font>");
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }
     return 1;

}

$editFormAction = $_SERVER['PHP_SELF'];
if (isset($_SERVER['QUERY_STRING'])) {
     $editFormAction .= "?" . htmlentities($_SERVER['QUERY_STRING']);
}

if ((isset($_POST["insert"])) && ($_POST["insert"] == "uploaditem")) {

    // Save user input on upload error
    foreach ($_POST as $key => $value) {
        $_SESSION['formdata'][$key] = $value;
    }

     fnProcess_IFile($_FILES);
     $file = $oFile;
     $filename = $_POST['item_name'];
     $created_dtg = date('m-d-Y_H:i:s');
     $itemuid = $created_dtg.$filename;
     $uniqueid = cy2_crypt($itemuid,'e');
     $insertSQL = sprintf("INSERT INTO items (item_name, item_description, item_document, item_dtg, item_category, type, item_unique_identifier) VALUES (%s, %s, %s, %s, %s, %s, %s)",
     GetSQLValueString($exercise,htmlentities($_POST['item_name']), "text"),
     GetSQLValueString($exercise,htmlentities($_POST['item_description']), "text"),
     GetSQLValueString($exercise,htmlentities($file), "text"),
     GetSQLValueString($exercise,htmlentities($_POST['item_dtg']), "text"),
     GetSQLValueString($exercise,htmlentities($_POST['item_category']), "text"),
     GetSQLValueString($exercise,htmlentities($_POST['multiselect5']), "text"),
     GetSQLValueString($exercise,htmlentities($uniqueid), "text"));

     mysqli_select_db($exercise,$database_exercise);
     $result = mysqli_query($exercise,$insertSQL);

     $query_itemdetail = sprintf("SELECT item_id FROM items WHERE item_unique_identifier = %s", GetSQLValueString($exercise,$uniqueid, "text"));
     $itemdetail = mysqli_query($exercise,$query_itemdetail) or die(mysqli_error());
     $row_itemdetail = mysqli_fetch_assoc($itemdetail);
     $totalRows_itemdetail = mysqli_num_rows($itemdetail);

     $vtno = $colname_vignette;
     $itemidentifier = $row_itemdetail['item_id'];
     $insertSQL1 = sprintf("INSERT INTO vignette_items (vignette_id, item_id) VALUES (%s, %s)",
     GetSQLValueString($exercise,htmlentities($vtno), "int"),
     GetSQLValueString($exercise,htmlentities($itemidentifier), "int"));

     $result1 = mysqli_query($exercise,$insertSQL1);

if (! empty($result)) {
          unset($_SESSION['formdata']);
          addMessage('<font color="#fadc73">Item Uploaded Successfully</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
} else {
          addMessage('<font color="red">Error Uploading Item</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
}
}


if ((isset($_POST["insert"])) && ($_POST["insert"] == "addactivity")) {


    // Save user input on upload error
    foreach ($_POST as $key => $value) {
        $_SESSION['formdata'][$key] = $value;
    }

     $filename = $_POST['activity_name'];
     $created_dtg = date('m-d-Y_H:i:s');
     $itemuid = $created_dtg.$filename;
     $uniqueid = cy2_crypt($itemuid,'e');
     $itemstatus = 'Incomplete';
     $insertSQL = sprintf("INSERT INTO items (item_name, item_description, item_dtg, item_category, item_unique_identifier, itemastatus) VALUES (%s, %s, %s, %s, %s, %s)",
     GetSQLValueString($exercise,htmlentities($_POST['activity_name']), "text"),
     GetSQLValueString($exercise,htmlentities($_POST['activity_description']), "text"),
     GetSQLValueString($exercise,htmlentities($_POST['activity_dtg']), "text"),
     GetSQLValueString($exercise,htmlentities($_POST['item_category']), "text"),
     GetSQLValueString($exercise,htmlentities($uniqueid), "text"),
     GetSQLValueString($exercise,htmlentities($itemstatus), "text"));

     mysqli_select_db($exercise,$database_exercise);
     $result2 = mysqli_query($exercise,$insertSQL);

     $query_itemdetail = sprintf("SELECT item_id FROM items WHERE item_unique_identifier = %s", GetSQLValueString($exercise,$uniqueid, "text"));
     $itemdetail = mysqli_query($exercise,$query_itemdetail) or die(mysqli_error());
     $row_itemdetail = mysqli_fetch_assoc($itemdetail);
     $totalRows_itemdetail = mysqli_num_rows($itemdetail);

     $vigtno = $colname_vignette;
     $itemidentifier = $row_itemdetail['item_id'];
     $insertSQL1 = sprintf("INSERT INTO vignette_items (vignette_id, item_id) VALUES (%s, %s)",
     GetSQLValueString($exercise,htmlentities($vigtno), "int"),
     GetSQLValueString($exercise,htmlentities($itemidentifier), "int"));

     $result3 = mysqli_query($exercise,$insertSQL1);

if (! empty($result2)) {
          unset($_SESSION['formdata']);
          addMessage('<font color="#fadc73">Activity Added Successfully</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
} else {
          addMessage('<font color="red">Error Adding Activity</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
}
}


function fnProcess_File($aFile)
{

     $maxsize = 15728640;

     // Define file upload path
     $sFile_Path = "player_products";

     // If no files uploaded return to caller
     if(empty($aFile))
          return;

     // Convert associative file array to numerically indexed
     $aFile = array_values($aFile);

     // If file size is empty return to caller
     if(empty($aFile[0]['size']))
          return;

    // allowed files types
     $allowedExts = array(
     "pdf",
     "doc",
     "docx",
     "txt",
     "ppt",
     "pptx",
     "xls",
     "xlsx",
     "jpg",
     "jpeg",
     "JPG",
     "JPEG",
     "png",
     "PNG",
     "pcap",
     "csv",
     );

     $extension = end(explode(".", $aFile[0]['name']));

     //checking if file to be uploaded extension is in allowed list
    if ( ! ( in_array($extension, $allowedExts ) ) ) {
          addMessage('<font color="red">Error Uploading File. File Extension Is Not Allowed. The Following File Extensions Are Allowed: pdf, doc, docx, txt, ppt, pptx, xls, xlsx, jpg, jpeg, png</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
    }

    //Check File size
    if($aFile[0]['size'] >= $maxsize) {
         addMessage('<font color="red">File Size Too Big</font>');
         header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
         exit();
    }

    // Define file name
    if(!empty($_POST['filename']))
    {
         // Get file extension
         global $oFile;
         $_POST['filename'] = str_replace(' ', "_", $_POST['filename']);
         $file = basename($_POST['filename'],".".$extension);
         $sFile = sprintf("%s/%s", $sFile_Path, $file.'.'.$extension);
         $oFile = sprintf($file.'.'.$extension);
    }

     // If the file already exists, bomb out
     if(file_exists($sFile)) {
          addMessage('<font color="red">File Already Exists,. Please Rename The File And Upload Again</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }

     // If the file can't be moved to it's location, bomb out
     if(!move_uploaded_file($aFile[0]['tmp_name'], $sFile)) {
          addMessage('<font color="red">Upload Failed. Unable To Move The Item</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }

     // If file permissions can't be changed, bomb out
     if(!chmod($sFile, 0444)) {
          addMessage('<font color="red">Upload Failed. Failed To Change Permission On Item</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }

     $allowedMimeTypes = array(
     "application/pdf",
     "application/msword",
     "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
     "text/plain",
     "text/csv",
     "application/vnd.ms-powerpoint",
     "application/vnd.openxmlformats-officedocument.presentationml.presentation",
     "application/vnd.ms-excel",
     "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
     "application/vnd.tcpdump.pcap",
     "image/jpeg",
     "image/png"
     );

     $mime = sprintf(mime_content_type($sFile));

     //checking if file to be uploaded mime type is in allowed list
     if (!in_array($mime, $allowedMimeTypes,TRUE)) {
          echo shell_exec("rm -rf $sFile");
          addMessage("<font color='red'>Error Uploading Item File. The Following MIME Type: '$mime' Is Not Allowed</font>");
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }
     return 1;

}

$editFormAction = $_SERVER['PHP_SELF'];
if (isset($_SERVER['QUERY_STRING'])) {
  $editFormAction .= "?" . htmlentities($_SERVER['QUERY_STRING']);
}

if ((isset($_POST["insert"])) && ($_POST["insert"] == "lessonidentified")) {
     $exlitype = "vignette";
     $insertSQL = sprintf("INSERT INTO exconli (exliname, exli_type, lesson_identified, recorded_by, dtg_recorded) VALUES (%s, %s, %s, %s, %s)",
     GetSQLValueString($exercise, htmlentities(cy2_crypt($_POST['exliname'],'d')), "text"),
     GetSQLValueString($exercise, htmlentities($exlitype), "text"),
     GetSQLValueString($exercise, htmlentities($_POST['lesson_identified']), "text"),
     GetSQLValueString($exercise, htmlentities($_POST['recorded_by']), "text"),
     GetSQLValueString($exercise, htmlentities($_POST['dtg_recorded']), "text"));

     mysqli_select_db($exercise,$database_exercise);
     $Result = mysqli_query($exercise,$insertSQL);
     if (! empty($Result)) {
          addMessage('<font color="#fadc73">Lesson Identified Recorded Successfully</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     } else {
          addMessage("<font color='red'>Error Recording Lesson Identified</font>");
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }
}

if ((isset($_POST["update"])) && ($_POST["update"] == "whitecardstatus")) {
     $wcstatus = $_POST['whitecard'];
     $updateSQL = sprintf("UPDATE vignette SET whitecard=%s WHERE vignette_id=%s",
     GetSQLValueString($exercise, htmlentities($_POST['whitecard']), "text"),
     GetSQLValueString($exercise, htmlentities(cy2_crypt($_POST['vignette_id'],'d')), "int"));

     mysqli_select_db($exercise,$database_exercise);
     $Result = mysqli_query($exercise,$updateSQL) or die(mysqli_error());
     if (! empty($Result) and $wcstatus == 'yes') {
          addMessage('<font color="#fadc73">White Card Enabled On The Vignette</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     } elseif (! empty($Result) and $wcstatus == '') {
          addMessage('<font color="#fadc73">White Card Disabled On The Vignette</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }
     else {
          addMessage('<font color="red">Error Enabling White Card</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }
}

if ((isset($_POST["update"])) && ($_POST["update"] == "updateactivitystatus")) {
     $acstatus = $_POST['itemastatus'];
     $actid = cy2_crypt($_POST['itemstatusid'],'d');
     $updateSQL = sprintf("UPDATE items SET itemastatus=%s WHERE item_id=%s",
     GetSQLValueString($exercise, htmlentities($_POST['itemastatus']), "text"),
     GetSQLValueString($exercise, htmlentities(cy2_crypt($_POST['itemstatusid'],'d')), "int"));

     mysqli_select_db($exercise,$database_exercise);

     $query_sa_activity = sprintf("SELECT v.vignette_name, vi.item_id, i.item_name FROM vignette v, vignette_items vi, items i WHERE vi.item_id = '$actid' AND vi.item_id=i.item_id AND v.vignette_id=vi.vignette_id");
     $sa_activity= mysqli_query($exercise, $query_sa_activity) or die(mysqli_error());
     $row_sa_activity = mysqli_fetch_assoc($sa_activity);
     $aname = $row_sa_activity['item_name'];
     $sname = $row_sa_activity['vignette_name'];
     $activity = "Activity: ".$aname. " ; Vignette: ".$sname;

     $insertSQL = sprintf("INSERT INTO live_feed (entry_dtg, title, description) VALUES (%s, %s, %s)",
     GetSQLValueString($exercise,htmlentities($_POST['entry_dtg']), "text"),
     GetSQLValueString($exercise,htmlentities($activity), "text"),
     GetSQLValueString($exercise,htmlentities($_POST['itemastatus']), "text"));
     mysqli_query($exercise, $insertSQL) or die(mysqli_error($exercise));

     $Result = mysqli_query($exercise,$updateSQL) or die(mysqli_error());
     if (! empty($Result) and $acstatus == 'Complete') {
          addMessage('<font color="#fadc73">Activity Marked As Complete</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     } elseif (! empty($Result) and $acstatus == 'Incomplete') {
          addMessage('<font color="#fadc73">Activity Marked As Incomplete</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }
     else {
          addMessage('<font color="red">Error Changing Activity Status</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }
}

if ((isset($_POST["update"])) && ($_POST["update"] == "updateinjectstatus")) {
     $mailstatus = $_POST['mail_status'];
     $injid = cy2_crypt($_POST['item_id'],'d');
     $updateSQL = sprintf("UPDATE email_injects SET mail_status=%s WHERE item_id=%s AND estype='vignette' AND typeid=$colname_vignette",
     GetSQLValueString($exercise, htmlentities($_POST['mail_status']), "text"),
     GetSQLValueString($exercise, htmlentities(cy2_crypt($_POST['item_id'],'d')), "int"));

     mysqli_select_db($exercise,$database_exercise);

     $query_sa_inject = sprintf("SELECT v.vignette_name, vi.item_id, i.item_name FROM vignette v, vignette_items vi, items i WHERE vi.item_id = '$injid' AND vi.item_id=i.item_id AND v.vignette_id=vi.vignette_id");
     $sa_inject= mysqli_query($exercise, $query_sa_inject) or die(mysqli_error());
     $row_sa_inject = mysqli_fetch_assoc($sa_inject);
     $aname = $row_sa_inject['item_name'];
     $sname = $row_sa_inject['vignette_name'];
     $inject = "Inject: ".$aname. " ; Vignette: ".$sname;

     $insertSQL = sprintf("INSERT INTO live_feed (entry_dtg, title, description) VALUES (%s, %s, %s)",
     GetSQLValueString($exercise,htmlentities($_POST['entry_dtg']), "text"),
     GetSQLValueString($exercise,htmlentities($inject), "text"),
     GetSQLValueString($exercise,htmlentities($mailstatus), "text"));
     mysqli_query($exercise, $insertSQL) or die(mysqli_error($exercise));

     $Result = mysqli_query($exercise,$updateSQL) or die(mysqli_error());
     if (! empty($Result) and $mailstatus == 'Deployed') {
          addMessage('<font color="#fadc73">Inject Marked As Deployed</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     } elseif (! empty($Result) and $mailstatus == 'Deploying') {
          addMessage('<font color="#fadc73">Inject Marked As Deploying</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }
     else {
          addMessage('<font color="red">Error Changing Inject Status</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }
}

if ((isset($_POST["update"])) && ($_POST["update"] == "updateartefactstatus")) {
     $artefactstatus = $_POST['status'];
     $artid = cy2_crypt($_POST['item_id'],'d');
     $updateSQL = sprintf("UPDATE place_artefacts SET status=%s WHERE item_id=%s AND estype='vignette' AND typeid=$colname_vignette",
     GetSQLValueString($exercise, htmlentities($_POST['status']), "text"),
     GetSQLValueString($exercise, htmlentities(cy2_crypt($_POST['item_id'],'d')), "int"));

     mysqli_select_db($exercise,$database_exercise);

     $query_sa_artefact = sprintf("SELECT v.vignette_name, vi.item_id, i.item_name FROM vignette v, vignette_items vi, items i WHERE vi.item_id = '$artid' AND vi.item_id=i.item_id AND v.vignette_id=vi.vignette_id");
     $sa_artefact= mysqli_query($exercise, $query_sa_artefact) or die(mysqli_error());
     $row_sa_artefact = mysqli_fetch_assoc($sa_artefact);
     $aname = $row_sa_artefact['item_name'];
     $sname = $row_sa_artefact['vignette_name'];
     $artefact = "Artefact: ".$aname. " ; Vignette: ".$sname;

     $insertSQL = sprintf("INSERT INTO live_feed (entry_dtg, title, description) VALUES (%s, %s, %s)",
     GetSQLValueString($exercise,htmlentities($_POST['entry_dtg']), "text"),
     GetSQLValueString($exercise,htmlentities($artefact), "text"),
     GetSQLValueString($exercise,htmlentities($artefactstatus), "text"));
     mysqli_query($exercise, $insertSQL) or die(mysqli_error($exercise));

     $Result = mysqli_query($exercise,$updateSQL) or die(mysqli_error());
     if (! empty($Result) and $artefactstatus == 'Deployed') {
          addMessage('<font color="#fadc73">Artefact Marked As Deployed</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     } elseif (! empty($Result) and $artefactstatus == 'Deploying') {
          addMessage('<font color="#fadc73">Artefact Marked As Deploying</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }
     else {
          addMessage('<font color="red">Error Changing Artefact Status</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }
}

if ((isset($_POST["insert"])) && ($_POST["insert"] == "addlogtask")) {
     $status = "to do";
     $insertSQL = sprintf("INSERT INTO exconlog (evt_id, vgt_id, log_detail, recorded_by, dtg_recorded, status) VALUES (%s, %s, %s, %s, %s, %s)",
     GetSQLValueString($exercise,htmlentities(cy2_crypt($_POST['evt_id'],'d')), "int"),
     GetSQLValueString($exercise,htmlentities(cy2_crypt($_POST['vgt_id'],'d')), "int"),
     GetSQLValueString($exercise,htmlentities($_POST['log_detail']), "text"),
     GetSQLValueString($exercise,htmlentities($_POST['recorded_by']), "text"),
     GetSQLValueString($exercise,htmlentities($_POST['dtg_recorded']), "text"),
     GetSQLValueString($exercise,htmlentities($status), "text"));

     mysqli_select_db($exercise,$database_exercise);
     $result5 = mysqli_query($exercise,$insertSQL);

if (! empty($result5)) {
          addMessage('<font color="#fadc73">Log Recorded Successfully</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
} else {
          addMessage("<font color='red'>Error Recording Log</font>");
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
}
}

if ((isset($_POST["update"])) && ($_POST["update"] == "changestatus")) {
     $updateSQL = sprintf("UPDATE exconlog SET status=%s WHERE exlog_id=%s",
     GetSQLValueString($exercise, htmlentities($_POST['status']), "text"),
     GetSQLValueString($exercise, htmlentities(cy2_crypt($_POST['exlog_id'],'d')), "int"));

     mysqli_select_db($exercise,$database_exercise);
     $Result = mysqli_query($exercise,$updateSQL) or die(mysqli_error());
     if (! empty($Result)) {
          addMessage('<font color="#fadc73">Status Changed Successfully</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }
     else {
          addMessage('<font color="red">Error Changing Status</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }
}

//attaching objectives to the events by inserting following data into event_objective table
if ((isset($_POST["insert"])) && ($_POST["insert"] == "assignobj")) {
     foreach ($_POST['multiselect6'] as $value){
     $insertSQL8 = sprintf("INSERT INTO vignette_objective (vignette_id, obj_id) VALUES (%s, %s)",
     GetSQLValueString($exercise,htmlentities(cy2_crypt($_POST['vigid'],'d')),"int"),
     GetSQLValueString($exercise,htmlentities(cy2_crypt($value,'d')), "int"));
     mysqli_select_db($exercise,$database_exercise);
     $Result = mysqli_query($exercise,$insertSQL8);
     }

     if (! empty($Result)) {
          addMessage('<font color="#fadc73">Objective Assigned To The Vignette Successfully</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     } else {
          addMessage($_POST['multiselect6'][0]);
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }
}

//attaching injects to the vignette by inserting following data into vignette_injects table
if ((isset($_POST["insert"])) && ($_POST["insert"] == "assigninject")) {
     foreach ($_POST['multiselect6'] as $value){
     $insertSQL = sprintf("INSERT INTO vignette_items (vignette_id, item_id) VALUES (%s, %s)",
     GetSQLValueString($exercise, htmlentities(cy2_crypt($_POST['vignette_id1'],'d')), "int"),
     GetSQLValueString($exercise,htmlentities(cy2_crypt($value,'d')), "int"));
     mysqli_select_db($exercise,$database_exercise);
     $Result = mysqli_query($exercise,$insertSQL);
     }

     if (! empty($Result)) {
          addMessage('<font color="#fadc73">Inject Assigned To The Vignette Successfully</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     } else {
          addMessage('<font color="red">Error Assigning Inject To The Vignette</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }
}

//attaching artefacts to the vignette by inserting following data into vignette_artefacts table
if ((isset($_POST["insert"])) && ($_POST["insert"] == "assignartefact")) {
     foreach ($_POST['multiselect6'] as $value){
     $insertSQL1 = sprintf("INSERT INTO vignette_items (vignette_id, item_id) VALUES (%s, %s)",
     GetSQLValueString($exercise,htmlentities(cy2_crypt($_POST['vignette_id2'],'d')), "int"),
     GetSQLValueString($exercise,htmlentities(cy2_crypt($value,'d')), "int"));
     mysqli_select_db($exercise,$database_exercise);
     $Result = mysqli_query($exercise,$insertSQL1);
     }

     if (! empty($Result)) {
          addMessage('<font color="#fadc73">Artefact Assigned To The Vignette Successfully</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     } else {
          addMessage('<font color="red">Error Assigning Artefact To The Vignette</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }
}

//attaching activity to the vignette by inserting following data into vignette_artefacts table
if ((isset($_POST["insert"])) && ($_POST["insert"] == "assignactivity")) {
     foreach ($_POST['multiselect6'] as $value){
     $insertSQL2 = sprintf("INSERT INTO vignette_items (vignette_id, item_id) VALUES (%s, %s)",
     GetSQLValueString($exercise,htmlentities(cy2_crypt($_POST['vignette_id3'],'d')), "int"),
     GetSQLValueString($exercise,htmlentities(cy2_crypt($value,'d')), "int"));
     mysqli_select_db($exercise,$database_exercise);
     $Result = mysqli_query($exercise,$insertSQL2);
     }

     if (! empty($Result)) {
          addMessage('<font color="#fadc73">Activity Assigned To The Vignette Successfully</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     } else {
          addMessage('<font color="red">Error Assigning Activity To The Vignette</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }
}

//assiging participants to the vignette by inserting following data into vignette_artefacts table
if ((isset($_POST["insert"])) && ($_POST["insert"] == "assignparticipants")) {

foreach ($_POST['multiselect6'] as $value){
$insertSQL1 = sprintf("INSERT INTO vignette_participants (vignette_id, pa_id) VALUES (%s, %s)",
     GetSQLValueString($exercise, htmlentities(cy2_crypt($_POST['vignette_id3'],'d')), "int"),
     GetSQLValueString($exercise, htmlentities(cy2_crypt($value,'d')), "int"));
     mysqli_select_db($exercise,$database_exercise);
     $Result = mysqli_query($exercise,$insertSQL1);
}

     if (! empty($Result)) {
          addMessage('<font color="#fadc73">Training Audience Assigned To The Vignette Successfully</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     } else {
          addMessage('<font color="red">Error Assigning Training Audience To The Vignette</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }
}

//attaching artefacts to the vignette by inserting following data into vignette_artefacts table
if ((isset($_POST["insert"])) && ($_POST["insert"] == "assignkt")) {
     foreach ($_POST['multiselect6'] as $value){
     $insertSQL1 = sprintf("INSERT INTO vignette_terrain (vignette_id, network) VALUES (%s, %s)",
     GetSQLValueString($exercise,htmlentities(cy2_crypt($_POST['vignette_id4'],'d')), "int"),
     GetSQLValueString($exercise,htmlentities(cy2_crypt($value,'d')), "text"));
     mysqli_select_db($exercise,$database_exercise);
     $Result = mysqli_query($exercise,$insertSQL1);
     }

     if (! empty($Result)) {
          addMessage('<font color="#fadc73">Key Terrain Assigned To The Vignette Successfully</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     } else {
          addMessage('<font color="red">Error Assigning Key Terrain To The Vignette</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }
}

//attaching artefacts to the vignette by inserting following data into vignette_artefacts table
if ((isset($_POST["insert"])) && ($_POST["insert"] == "assignpersona")) {
     foreach ($_POST['multiselect6'] as $value){
     $insertSQL1 = sprintf("INSERT INTO vignette_persona_group (vignette_id, group_id, exercise_name) VALUES (%s, %s, %s)",
     GetSQLValueString($exercise,htmlentities(cy2_crypt($_POST['vignette_id5'],'d')), "int"),
     GetSQLValueString($exercise,htmlentities(cy2_crypt($value,'d')), "text"),
     GetSQLValueString($exercise,htmlentities(cy2_crypt($_POST['exercise_name'],'d')), "text"));
     mysqli_select_db($exercise,$database_exercise);
     $Result = mysqli_query($exercise,$insertSQL1);
     }

     if (! empty($Result)) {
          addMessage('<font color="#fadc73">Persona Assigned To The Vignette Successfully</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     } else {
          addMessage('<font color="red">Error Assigning Persona To The Vignette</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }
}

if ((isset($_POST["insert"])) && ($_POST["insert"] == "uploadproduct")) {

    // Save user input on upload error
    foreach ($_POST as $key => $value) {
        $_SESSION['formdata'][$key] = $value;
    }

     fnProcess_File($_FILES);
     $file = $oFile;
     $insertSQL = sprintf("INSERT INTO player_product (name, description, date_submitted, training_audience, file, vignette_id) VALUES (%s, %s, %s, %s, %s, %s)",
     GetSQLValueString($exercise,htmlentities($_POST['name']), "text"),
     GetSQLValueString($exercise,htmlentities($_POST['description']), "text"),
     GetSQLValueString($exercise,htmlentities($_POST['date_submitted']), "text"),
     GetSQLValueString($exercise,htmlentities($_POST['training_audience']), "text"),
     GetSQLValueString($exercise,htmlentities($file), "text"),
     GetSQLValueString($exercise,htmlentities(cy2_crypt($_POST['vignette_id'],'d')), "int"));

     mysqli_select_db($exercise,$database_exercise);
     $result = mysqli_query($exercise,$insertSQL);

if (! empty($result)) {
          unset($_SESSION['formdata']);
          addMessage('<font color="#fadc73">Player Product Uploaded Successfully</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
} else {
          addMessage("<font color='red'>Error Creating And Uploading Player Product</font>");
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
}
}

if ((isset($_POST["insert"])) && ($_POST["insert"] == "exmanews")) {

     $table = $_POST['table'];
     $insertSQL = sprintf("INSERT INTO $table (headline, author, date, par1, img) VALUES (%s, %s, %s, %s, %s)",
     GetSQLValueString($exmanews,htmlentities($_POST['headline']), "text"),
     GetSQLValueString($exmanews,htmlentities($_POST['author']), "text"),
     GetSQLValueString($exmanews,htmlentities($_POST['date']), "text"),
     GetSQLValueString($exmanews,htmlentities($_POST['par1']), "text"),
     GetSQLValueString($exmanews,htmlentities($_POST['img']), "int"));

     mysqli_select_db($exmanews,$database_exmanews);
     $result = mysqli_query($exmanews,$insertSQL);

if (! empty($result)) {
          addMessage('<font color="#fadc73">News Uploaded Successfully</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
} else {
          addMessage('<font color="red">Error Updating News</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
}
}

if ((isset($_POST["insert"])) && ($_POST["insert"] == "deployartefact")) {
     $vigtype = 'vignette';
     $insertSQL = sprintf("INSERT INTO place_artefacts (item_id, remote_network, remote_node, remote_dir, status, estype, typeid) VALUES (%s, %s, %s, %s, %s, %s, %s)",
     GetSQLValueString($exercise,htmlentities(cy2_crypt($_POST['item_id'],'d')), "int"),
     GetSQLValueString($exercise,htmlentities($_POST['remote_network']), "text"),
     GetSQLValueString($exercise,htmlentities($_POST['remote_node']), "text"),
     GetSQLValueString($exercise,htmlentities($_POST['remote_dir']), "text"),
     GetSQLValueString($exercise,htmlentities($_POST['status']), "text"),
     GetSQLValueString($exercise,htmlentities($vigtype), "text"),
     GetSQLValueString($exercise,htmlentities(cy2_crypt($_POST['typeid'],'d')), "int"));
     $aid= cy2_crypt($_POST['item_id'],'d');
     mysqli_select_db($exercise,$database_exercise);
     $result = mysqli_query($exercise,$insertSQL);

     $query_sa_artefacts = sprintf("SELECT v.vignette_name, vi.item_id, i.item_name FROM vignette v, vignette_items vi, items i WHERE vi.item_id = '$aid' AND vi.item_id=i.item_id AND v.vignette_id=vi.vignette_id");
     $sa_artefacts= mysqli_query($exercise, $query_sa_artefacts) or die(mysqli_error());
     $row_sa_artefacts = mysqli_fetch_assoc($sa_artefacts);
     $aname = $row_sa_artefacts['item_name'];
     $sname = $row_sa_artefacts['vignette_name'];
     $artefact = "Artefact: ".$aname. " ; vignette: ".$sname;

     $insertSQL = sprintf("INSERT INTO live_feed (entry_dtg, title, description) VALUES (%s, %s, %s)",
     GetSQLValueString($exercise,htmlentities($_POST['entry_dtg']), "text"),
     GetSQLValueString($exercise,htmlentities($artefact), "text"),
     GetSQLValueString($exercise,htmlentities($_POST['description']), "text"));
     mysqli_query($exercise, $insertSQL) or die(mysqli_error($exercise));
     if (! empty($result)) {
          addMessage('<font color="#fadc73">Artefacts Deployed Successfully</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     } else {
          addMessage('<font color="red">Error Deploying Artefact</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }
}

if ((isset($_POST["insert"])) && ($_POST["insert"] == "injectemail")) {
    if (filter_var($_POST['mail_sender'], FILTER_VALIDATE_EMAIL)) {
        $recipientEmails = explode(';', $_POST['mail_recipient']);
        $valid = true;
        foreach ($recipientEmails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $valid = false;
                break;
            }
        }
     $vigtype = 'vignette';
     $insertSQL = sprintf("INSERT INTO email_injects (item_id, mail_sender, mail_recipient, mail_subject, mail_message, mail_status, estype, typeid) VALUES (%s, %s, %s, %s, %s, %s, %s, %s)",
     GetSQLValueString($exercise,htmlentities(cy2_crypt($_POST['item_id'],'d')), "int"),
     GetSQLValueString($exercise,htmlentities($_POST['mail_sender']), "text"),
     GetSQLValueString($exercise,htmlentities($_POST['mail_recipient']), "text"),
     GetSQLValueString($exercise,htmlentities($_POST['mail_subject']), "text"),
     GetSQLValueString($exercise,htmlentities($_POST['mail_message']), "text"),
     GetSQLValueString($exercise,htmlentities($_POST['mail_status']), "text"),
     GetSQLValueString($exercise,htmlentities($vigtype), "text"),
     GetSQLValueString($exercise,htmlentities(cy2_crypt($_POST['typeid'],'d')), "int"));
     $iid= cy2_crypt($_POST['item_id'],'d');
     mysqli_select_db($exercise,$database_exercise);
     $result = mysqli_query($exercise,$insertSQL);
     $query_si_injects = sprintf("SELECT v.vignette_name, vi.item_id, i.item_name FROM vignette v, vignette_items vi, items i WHERE vi.item_id = '$iid' AND vi.item_id=i.item_id AND v.vignette_id=vi.vignette_id");
     $si_injects= mysqli_query($exercise, $query_si_injects) or die(mysqli_error());
     $row_si_injects = mysqli_fetch_assoc($si_injects);
     $iname = $row_si_injects['item_name'];
     $sname = $row_si_injects['vignette_name'];
     $inject = "Inject: ".$iname. " ; vignette: ".$sname;

     $insertSQL = sprintf("INSERT INTO live_feed (entry_dtg, title, description) VALUES (%s, %s, %s)",
     GetSQLValueString($exercise,htmlentities($_POST['entry_dtg']), "text"),
     GetSQLValueString($exercise,htmlentities($inject), "text"),
     GetSQLValueString($exercise,htmlentities($_POST['description']), "text"));
     mysqli_query($exercise, $insertSQL) or die(mysqli_error($exercise));
if (! empty($result)) {
        addMessage('<font color="#fadc73">Email Inject Task Created Successfully</font>');
        header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
        exit();
} else {
        addMessage('<font color="red">Error Creating Email Inject Task</font>');
        header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
        exit();
}
}
}

if ((isset($_POST["insert"])) && ($_POST["insert"] == "email")) {
    if (filter_var($_POST['sender'], FILTER_VALIDATE_EMAIL)) {
        $recipientEmails = explode(';', $_POST['recipient']);
        $valid = true;
        foreach ($recipientEmails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $valid = false;
                break;
            }
        }
     $insertSQL = sprintf("INSERT INTO email (vignette_id, sender, recipient, subject, message) VALUES (%s, %s, %s, %s, %s)",
     GetSQLValueString($exercise,htmlentities(cy2_crypt($_POST['vignette_id'],'d')), "int"),
     GetSQLValueString($exercise,htmlentities($_POST['sender']), "text"),
     GetSQLValueString($exercise,htmlentities($_POST['recipient']), "text"),
     GetSQLValueString($exercise,htmlentities($_POST['subject']), "text"),
     GetSQLValueString($exercise,htmlentities($_POST['message']), "text"));

     mysqli_select_db($exercise,$database_exercise);
     $result = mysqli_query($exercise,$insertSQL);
if (! empty($result)) {
          addMessage('<font color="#fadc73">Email Scheduled To Be Sent</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
} else {
          addMessage('<font color="red">Error Scheduling Email</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
}
}
}

if ((isset($_POST["insert"])) && ($_POST["insert"] == "vulnify")) {
     $insertSQL = sprintf("INSERT INTO vulnify (vignette_id, ap_id, target, network, status) VALUES (%s, %s, %s, %s, %s)",
     GetSQLValueString($exercise,htmlentities(cy2_crypt($_POST['vignette_id'],'d')), "int"),
     GetSQLValueString($exercise,htmlentities($_POST['ap_id']), "int"),
     GetSQLValueString($exercise,htmlentities($_POST['target']), "text"),
     GetSQLValueString($exercise,htmlentities($_POST['network']), "text"),
     GetSQLValueString($exercise,htmlentities($_POST['status']), "int"));
     $sid= cy2_crypt($_POST['vignette_id'],'d');
     mysqli_select_db($exercise,$database_exercise);
     $result = mysqli_query($exercise,$insertSQL);

     mysqli_select_db($exmato,$database_exmato);
     $exname = $exercisedetail['slug'];
     $query_ap = sprintf("SELECT ap_name, vignette_name FROM exmato.ansible_playbook, `$exname`.vulnify, `$exname`.vignette  WHERE `$exname`.vulnify.vignette_id =$sid AND `$exname`.vulnify.vignette_id = `$exname`.vignette.vignette_id AND exmato.ansible_playbook.ap_id=`$exname`.vulnify.ap_id");
     $ap= mysqli_query($exercise, $query_ap);
     $row_ap = mysqli_fetch_assoc($ap);
     $pbook= $row_ap['ap_name'];
     $sname = $row_ap['vignette_name'];
     $vulnify = "Vulnify: ".$pbook. " ; vignette: ".$sname;

     $insertSQL = sprintf("INSERT INTO live_feed (entry_dtg, title, description) VALUES (%s, %s, %s)",
     GetSQLValueString($exercise,htmlentities($_POST['entry_dtg']), "text"),
     GetSQLValueString($exercise,htmlentities($vulnify), "text"),
     GetSQLValueString($exercise,htmlentities($_POST['description']), "text"));
     mysqli_query($exercise, $insertSQL) or die(mysqli_error($exercise));
if (! empty($result)) {
          addMessage('<font color="#fadc73">Vulnify Configuration In Progress</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
} else {
          addMessage('<font color="red">Error Vulnifying</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
}
}

if ((isset($_POST["update"])) && ($_POST["update"] == "unlinkall")) {
     $eventvalue = "";
     $deleteSQL = sprintf("DELETE FROM vignette_items WHERE vignette_id=%s",
     GetSQLValueString($exercise, htmlentities(cy2_crypt($_POST['vignette_id'],'d')), "int"));

     $delete1SQL = sprintf("DELETE FROM email WHERE vignette_id=%s",
     GetSQLValueString($exercise, htmlentities(cy2_crypt($_POST['vignette_id'],'d')), "int"));

     $delete2SQL = sprintf("DELETE FROM vulnify WHERE vignette_id=%s",
     GetSQLValueString($exercise, htmlentities(cy2_crypt($_POST['vignette_id'],'d')), "int"));

     $delete3SQL = sprintf("DELETE FROM vignette_participants WHERE vignette_id=%s",
     GetSQLValueString($exercise, htmlentities(cy2_crypt($_POST['vignette_id'],'d')), "int"));

     $delete4SQL = sprintf("DELETE FROM vignette_terrain WHERE vignette_id=%s",
     GetSQLValueString($exercise, htmlentities(cy2_crypt($_POST['vignette_id'],'d')), "int"));

     $delete5SQL = sprintf("DELETE FROM vignette_objective WHERE vignette_id=%s",
     GetSQLValueString($exercise, htmlentities(cy2_crypt($_POST['vignette_id'],'d')), "int"));

     $delete6SQL = sprintf("DELETE FROM player_product WHERE vignette_id=%s",
     GetSQLValueString($exercise, htmlentities(cy2_crypt($_POST['vignette_id'],'d')), "int"));

     $delete7SQL = sprintf("DELETE FROM vignette_persona_group WHERE vignette_id=%s",
     GetSQLValueString($exercise, htmlentities(cy2_crypt($_POST['vignette_id'],'d')), "int"));

     mysqli_select_db($exercise,$database_exercise);
     $result = mysqli_query($exercise,$deleteSQL);
     $result1 = mysqli_query($exercise,$delete1SQL);
     $result2 = mysqli_query($exercise,$delete2SQL);
     $result3 = mysqli_query($exercise,$delete3SQL);
     $result4 = mysqli_query($exercise,$delete4SQL);
     $result5 = mysqli_query($exercise,$delete5SQL);
     $result6 = mysqli_query($exercise,$delete6SQL);
     $result7 = mysqli_query($exercise,$delete7SQL);

     if (! empty($result)) {
          addMessage('<font color="#fadc73">Vignette Objects Unlinked Successfully</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
} else {
          addMessage('<font color="red">Error Unlinking Objects From The Vignette</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
}
}

if ((isset($_POST['productid'])) && ($_POST['productid'] != "")) {
     $updatefile = $_POST['file'];
     echo shell_exec("rm -rf player_products/".$updatefile);
     $deleteSQL = sprintf("DELETE FROM player_product WHERE productid=%s",
     GetSQLValueString($exercise, htmlentities(cy2_crypt($_POST['productid'],'d')), "int"));

     mysqli_select_db($exercise,$database_exercise);
     $result = mysqli_query($exercise,$deleteSQL) or die(mysqli_error());

     if (! empty($result)) {
          addMessage('<font color="#fadc73">Player Product Deleted Successfully</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
} else {
          addMessage('<font color="red">Error Deleting Player Product</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
}
}

if ((isset($_POST['vignette_items_id'])) && ($_POST['vignette_items_id'] != "")) {
     $deleteSQL = sprintf("DELETE FROM vignette_items WHERE vignette_items_id=%s",
     GetSQLValueString($exercise,htmlentities(cy2_crypt($_POST['vignette_items_id'],'d')), "int"));

     mysqli_select_db($exercise,$database_exercise);
     $result = mysqli_query($exercise,$deleteSQL) or die(mysqli_error());

     if (! empty($result)) {
          addMessage('<font color="#fadc73">Vignette Item Unlinked Successfully</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
} else {
          addMessage('<font color="red">Error Unlinking Vignette Item</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
}
}

if ((isset($_POST['vo_id'])) && ($_POST['vo_id'] != "")) {
     $deleteSQL = sprintf("DELETE FROM vignette_objective WHERE vo_id=%s",
     GetSQLValueString($exercise, htmlentities(cy2_crypt($_POST['vo_id'],'d')), "int"));

     mysqli_select_db($exercise,$database_exercise);
     $result = mysqli_query($exercise,$deleteSQL) or die(mysqli_error());

     if (! empty($result)) {
          addMessage('<font color="#fadc73">Vignette Objective Removed Successfully</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
} else {
          addMessage('<font color="red">Error Removing Vignette Objective</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
}
}

if ((isset($_POST['exlog_id'])) && ($_POST['exlog_id'] != "")) {
     $deleteSQL = sprintf("DELETE FROM exconlog WHERE exlog_id=%s",
     GetSQLValueString($exercise,htmlentities(cy2_crypt($_POST['exlog_id'],'d')), "int"));

     mysqli_select_db($exercise,$database_exercise);
     $result = mysqli_query($exercise,$deleteSQL) or die(mysqli_error());

     if (! empty($result)) {
          addMessage('<font color="#fadc73">Excon Task Deleted Successfully</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
} else {
          addMessage('<font color="red">Error Deleteing Excon Task</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
}
}

if ((isset($_POST['email_inj_id'])) && ($_POST['email_inj_id'] != "")) {
     $deleteSQL = sprintf("DELETE FROM email_injects WHERE email_inj_id=%s",
     GetSQLValueString($exercise,htmlentities(cy2_crypt($_POST['email_inj_id'],'d')), "int"));

     mysqli_select_db($exercise,$database_exercise);
     $result = mysqli_query($exercise,$deleteSQL) or die(mysqli_error());

     if (! empty($result)) {
          addMessage('<font color="#fadc73">Email Inject Unlinked Successfully</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
} else {
          addMessage('<font color="red">Error Unlinking Email Inject</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
}
}

if ((isset($_POST['email_id'])) && ($_POST['email_id'] != "")) {
     $deleteSQL = sprintf("DELETE FROM email WHERE email_id=%s",
     GetSQLValueString($exercise,htmlentities(cy2_crypt($_POST['email_id'],'d')), "int"));

     mysqli_select_db($exercise,$database_exercise);
     $result = mysqli_query($exercise,$deleteSQL) or die(mysqli_error());

     if (! empty($result)) {
          addMessage('<font color="#fadc73">Scheduled Email Deleted Successfully</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
} else {
          addMessage('<font color="red">Error Deleting Scheduled Email</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
}
}

if ((isset($_POST['pl_art_id'])) && ($_POST['pl_art_id'] != "")) {
     $deleteSQL = sprintf("DELETE FROM place_artefacts WHERE pl_art_id=%s",
     GetSQLValueString($exercise,htmlentities(cy2_crypt($_POST['pl_art_id'],'d')), "int"));

     mysqli_select_db($exercise,$database_exercise);
     $result = mysqli_query($exercise,$deleteSQL) or die(mysqli_error());

     if (! empty($result)) {
          addMessage('<font color="#fadc73">Artefact Placement Task Aborted Successfully</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
} else {
          addMessage('<font color="red">Error Aborting Aretfact Placement Task</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
}
}

if ((isset($_POST['vulnify_id'])) && ($_POST['vulnify_id'] != "")) {
     $deleteSQL = sprintf("DELETE FROM vulnify WHERE vulnify_id=%s",
     GetSQLValueString($exercise,htmlentities(cy2_crypt($_POST['vulnify_id'],'d')), "int"));

     mysqli_select_db($exercise,$database_exercise);
     $result = mysqli_query($exercise,$deleteSQL) or die(mysqli_error());

     if (! empty($result)) {
          addMessage('<font color="#fadc73">Vulnify Task Aborted Successfully</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
} else {
          addMessage('<font color="red">Error Aborting Vulnify Task</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
}
}

if ((isset($_POST['vignette_participants_id'])) && ($_POST['vignette_participants_id'] != "")) {
     $deleteSQL = sprintf("DELETE FROM vignette_participants WHERE vignette_participants_id=%s",
     GetSQLValueString($exercise,htmlentities(cy2_crypt($_POST['vignette_participants_id'],'d')), "int"));

     mysqli_select_db($exercise, $database_exercise);
     $Result = mysqli_query($exercise, $deleteSQL) or die(mysqli_error());
     if (! empty($Result)) {
          addMessage('<font color="#fadc73">Training Audience Unlinked Successfully</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     } else {
          addMessage('<font color="red">Error Unlinking Training Audience From The Vignette</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }
}

if ((isset($_POST['vignette_terrain_id'])) && ($_POST['vignette_terrain_id'] != "")) {
     $deleteSQL = sprintf("DELETE FROM vignette_terrain WHERE vignette_terrain_id=%s",
     GetSQLValueString($exercise,htmlentities(cy2_crypt($_POST['vignette_terrain_id'],'d')), "int"));

     mysqli_select_db($exercise, $database_exercise);
     $Result = mysqli_query($exercise, $deleteSQL) or die(mysqli_error());
     if (! empty($Result)) {
          addMessage('<font color="#fadc73">Key Terrain Unlinked Successfully</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     } else {
          addMessage('<font color="red">Error Unlinking Key Terrain</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }
}

if ((isset($_POST['vignette_persona_id'])) && ($_POST['vignette_persona_id'] != "")) {
     $deleteSQL = sprintf("DELETE FROM vignette_persona_group WHERE vignette_persona_id=%s",
     GetSQLValueString($exercise,htmlentities(cy2_crypt($_POST['vignette_persona_id'],'d')), "int"));

     mysqli_select_db($exercise, $database_exercise);
     $Result = mysqli_query($exercise, $deleteSQL) or die(mysqli_error());
     if (! empty($Result)) {
          addMessage('<font color="#fadc73">Persona Unlinked Successfully</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     } else {
          addMessage('<font color="red">Error Unlinking Persona</font>');
          header('Location:'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
          exit();
     }
}

mysqli_select_db($exercise,$database_exercise);
$query_vignette = sprintf("SELECT * FROM vignette WHERE vignette_id = %s", GetSQLValueString($exercise,$colname_vignette, "int"));
$vignette = mysqli_query($exercise,$query_vignette) or die(mysqli_error());
$row_vignette = mysqli_fetch_assoc($vignette);
$totalRows_vignette = mysqli_num_rows($vignette);
$eventsd = $row_vignette['vignette_start_date'];
$eventfd = $row_vignette['vignette_finish_date'];
$vignetteid= $row_vignette['vignette_id'];
$vignettename = $row_vignette['vignette_name'];
$video= $row_vignette['video'];


//Displaying video list on a table
$query_vignettevideo = sprintf("SELECT s.vignette_id, s.vignette_name, v.video, v.videoname, v.videofile FROM vignette s, videos v WHERE s.vignette_id = '$vignetteid' AND s.video=v.video");
$vignettevideo= mysqli_query($exercise, $query_vignettevideo);
$row_vignettevideo = mysqli_fetch_assoc($vignettevideo);
$totalRows_vignettevideo = mysqli_num_rows($vignettevideo);

$query_vignette_item = "SELECT vi.vignette_id, vi.item_id, i.item_name, i.item_description, i.item_document, i.item_dtg, i.item_category, i.item_location FROM vignette_items vi, items i WHERE vi.vignette_id = $vignetteid AND vi.item_id=i.item_id ORDER BY i.item_dtg";
$vignette_item = mysqli_query($exercise,$query_vignette_item);
$row_vignette_item = mysqli_fetch_assoc($vignette_item);
$totalRows_vignette_item = mysqli_num_rows($vignette_item);

$query_vignette_item1 = "SELECT vi.vignette_items_id, vi.vignette_id, vi.item_id, i.item_name, i.item_description, i.item_document, i.item_dtg, i.item_category, i.type, i.item_location, i.itemastatus FROM vignette_items vi, items i WHERE vi.vignette_id = $vignetteid AND vi.item_id=i.item_id ORDER BY i.item_dtg ASC";
$vignette_item1 = mysqli_query($exercise,$query_vignette_item1);
$row_vignette_item1 = mysqli_fetch_assoc($vignette_item1);
$totalRows_vignette_item1 = mysqli_num_rows($vignette_item1);


////////////////////
$query_vignette_injects = "SELECT i.item_id, i.item_name, i.item_description, i.item_document, i.item_dtg, i.item_category, i.type, i.item_location, ti.item_type, ti.icon, ti.colour FROM  vignette_items vi , items i, type_item ti  WHERE vi.vignette_id = $vignetteid AND vi.item_id=i.item_id AND i.item_category = 'inject' AND i.type=ti.item_type ORDER BY i.item_dtg ASC";
$vignette_injects = mysqli_query($exercise,$query_vignette_injects);
$row_vignette_injects = mysqli_fetch_assoc($vignette_injects);
$totalRows_vignette_injects = mysqli_num_rows($vignette_injects);

$query_vignette_injects1 = "SELECT i.item_id, i.item_name, i.type, ei.mail_sender, ei.mail_recipient,ei.mail_subject, ei.mail_message, ei.mail_status FROM vignette_items vi, items i, email_injects ei WHERE vi.vignette_id = $vignetteid AND vi.item_id= i.item_id AND i.item_id = ei.item_id";
$vignette_injects1 = mysqli_query($exercise,$query_vignette_injects1);
$row_vignette_injects1 = mysqli_fetch_assoc($vignette_injects1);
$totalRows_vignette_injects1 = mysqli_num_rows($vignette_injects1);

//////////////////////////////////////////////////////////////

$query_vignette_injects2 = "SELECT i.item_id, i.item_name, i.type, ei.mail_sender, ei.mail_recipient,ei.mail_subject, ei.mail_message, ei.mail_status FROM vignette_items vi, items i, email_injects ei WHERE vi.vignette_id = $vignetteid AND vi.item_id= i.item_id AND i.item_id = ei.item_id AND ei.mail_status='Deployed' AND ei.estype='vignette' AND ei.typeid = $vignetteid ORDER BY ei.email_inj_id desc";
$vignette_injects2 = mysqli_query($exercise,$query_vignette_injects2);
$row_vignette_injects2 = mysqli_fetch_assoc($vignette_injects2);
$totalRows_vignette_injects2 = mysqli_num_rows($vignette_injects2);
$vignetteinjid = $row_vignette_injects2['item_id'];

$query_vignette_injects3 = "SELECT i.item_id, i.item_name, i.type, ei.email_inj_id, ei.mail_sender, ei.mail_recipient,ei.mail_subject, ei.mail_message, ei.mail_status FROM vignette_items vi, items i, email_injects ei WHERE vi.vignette_id = $vignetteid AND vi.item_id= i.item_id AND i.item_id = ei.item_id AND ei.mail_status='Deploying' AND ei.estype='vignette' AND ei.typeid = $vignetteid ORDER BY ei.email_inj_id desc";
$vignette_injects3 = mysqli_query($exercise,$query_vignette_injects3);
$row_vignette_injects3 = mysqli_fetch_assoc($vignette_injects3);
$totalRows_vignette_injects3 = mysqli_num_rows($vignette_injects3);

$query_vignette_injects4 = "SELECT i.item_id, i.item_name, i.type, ei.mail_sender, ei.mail_recipient,ei.mail_subject, ei.mail_message, ei.mail_status FROM vignette_items vi, items i, email_injects ei WHERE vi.vignette_id = $vignetteid AND vi.item_id= i.item_id AND i.item_id = ei.item_id AND ei.mail_status='Deploying' or ei.mail_status='Deployed' ORDER BY ei.email_inj_id desc";
$vignette_injects4 = mysqli_query($exercise,$query_vignette_injects4);
$row_vignette_injects4 = mysqli_fetch_assoc($vignette_injects4);
$totalRows_vignette_injects4 = mysqli_num_rows($vignette_injects4);

$query_vignette_injects5 = "SELECT * from items WHERE item_category='inject' ORDER BY item_name ASC";
$vignette_injects5 = mysqli_query($exercise,$query_vignette_injects5);
$row_vignette_injects5 = mysqli_fetch_assoc($vignette_injects5);
$totalRows_vignette_injects5 = mysqli_num_rows($vignette_injects5);

//////////////////////////////////////////////////////////////////////////////////////

//Displaying Artefacts list on a table
$query_vignette_artefacts = "SELECT i.item_id, i.item_name, i.item_description, i.item_document, i.item_dtg, i.item_category, i.type, i.item_location, ti.item_type, ti.icon, ti.colour FROM  vignette_items vi , items i, type_item ti WHERE vi.vignette_id = $vignetteid AND vi.item_id=i.item_id AND i.item_category = 'artefact' AND i.type=ti.item_type ORDER BY i.item_dtg ASC";
$vignette_artefacts = mysqli_query($exercise,$query_vignette_artefacts);
$row_vignette_artefacts = mysqli_fetch_assoc($vignette_artefacts);
$totalRows_vignette_artefacts = mysqli_num_rows($vignette_artefacts);

//Displaying Artefacts list on a table
$query_vignette_artefacts1 = "SELECT * from items WHERE item_category='artefact' ORDER BY item_name ASC";
$vignette_artefacts1 = mysqli_query($exercise,$query_vignette_artefacts1);
$row_vignette_artefacts1 = mysqli_fetch_assoc($vignette_artefacts1);
$totalRows_vignette_artefacts1 = mysqli_num_rows($vignette_artefacts1);

//Displaying Artefacts list on a table
$query_vignette_artefacts2 = "SELECT pa.pl_art_id, pa.remote_network, pa.remote_node, pa.remote_dir, pa.status, i.item_name FROM vignette v, vignette_items vi, items i, place_artefacts pa WHERE v.vignette_id = $vignetteid AND v.vignette_id=vi.vignette_id AND vi.item_id=i.item_id AND i.item_id=pa.item_id AND pa.status='Deploying' AND pa.typeid = $vignetteid ORDER BY pa.pl_art_id DESC";
$vignette_artefacts2 = mysqli_query($exercise,$query_vignette_artefacts2);
$row_vignette_artefacts2 = mysqli_fetch_assoc($vignette_artefacts2);
$totalRows_vignette_artefacts2 = mysqli_num_rows($vignette_artefacts2);


//Displaying Artefacts list on a table
$query_vignette_artefacts3 = "SELECT pa.pl_art_id, pa.remote_network, pa.remote_node, pa.remote_dir, pa.status, i.item_name FROM vignette v, vignette_items vi, items i, place_artefacts pa WHERE v.vignette_id = $vignetteid AND v.vignette_id=vi.vignette_id AND vi.item_id=i.item_id AND i.item_id=pa.item_id AND pa.status='Deployed' AND pa.typeid = $vignetteid ORDER BY pa.pl_art_id DESC";
$vignette_artefacts3 = mysqli_query($exercise,$query_vignette_artefacts3);
$row_vignette_artefacts3 = mysqli_fetch_assoc($vignette_artefacts3);
$totalRows_vignette_artefacts3 = mysqli_num_rows($vignette_artefacts3);

$query_vignette_artefacts4 = "SELECT pa.pl_art_id, pa.remote_network, pa.remote_node, pa.remote_dir, pa.status, i.item_name FROM vignette v, vignette_items vi, items i, place_artefacts pa WHERE v.vignette_id = $vignetteid AND v.vignette_id=vi.vignette_id AND vi.item_id=i.item_id AND i.item_id=pa.item_id AND pa.status='Deploying' ORDER BY pa.pl_art_id DESC";
$vignette_artefacts4 = mysqli_query($exercise,$query_vignette_artefacts4);
$row_vignette_artefacts4 = mysqli_fetch_assoc($vignette_artefacts4);
$totalRows_vignette_artefacts4 = mysqli_num_rows($vignette_artefacts4);

//Displaying emails on a table
$query_vignette_email = "SELECT s.vignette_id, e.sender, e.recipient, e.subject, e.message, e.email_status FROM vignette s, email e WHERE s.vignette_id = $vignetteid AND s.vignette_id=e.vignette_id AND e.email_status='Sent' ORDER BY email_id DESC";
$vignette_email = mysqli_query($exercise,$query_vignette_email);
$row_vignette_email = mysqli_fetch_assoc($vignette_email);
$totalRows_vignette_email = mysqli_num_rows($vignette_email);


//Displaying emails on a table with status sending
$query_vignette_email1 = "SELECT s.vignette_id, e.email_id, e.sender, e.recipient, e.subject, e.message, e.email_status FROM vignette s, email e WHERE s.vignette_id = $vignetteid AND s.vignette_id=e.vignette_id AND e.email_status='Unsent' ORDER BY email_id DESC";
$vignette_email1 = mysqli_query($exercise,$query_vignette_email1);
$row_vignette_email1 = mysqli_fetch_assoc($vignette_email1);
$totalRows_vignette_email1 = mysqli_num_rows($vignette_email1);


//Displaying Participants list on a table
$query_vignette_participants1 = "SELECT sp.vignette_participants_id, p.pa_name, p.pa_type, tt.trgaudience_type, tt.colour, tt.icon FROM vignette s, vignette_participants sp, participants p, type_trgaudience tt  WHERE s.vignette_id = $vignetteid AND s.vignette_id=sp.vignette_id AND sp.pa_id=p.pa_id AND p.pa_type =tt.id ";
$vignette_participants1= mysqli_query($exercise,$query_vignette_participants1);
$row_vignette_participants1 = mysqli_fetch_assoc($vignette_participants1);
$totalRows_vignette_participants1 = mysqli_num_rows($vignette_participants1);

//Displaying Participants list on a modal
$query_vignette_participants2 = "SELECT sp.vignette_participants_id, p.pa_name, p.pa_type FROM vignette s, vignette_participants sp, participants p  WHERE s.vignette_id = $vignetteid AND s.vignette_id=sp.vignette_id AND sp.pa_id=p.pa_id ";
$vignette_participants2= mysqli_query($exercise,$query_vignette_participants2);
$row_vignette_participants2 = mysqli_fetch_assoc($vignette_participants2);
$totalRows_vignette_participants2 = mysqli_num_rows($vignette_participants2);

//Displaying IMG for vignette
$query_vignetteimg = sprintf("SELECT * FROM vignette s, image_vignette imgs WHERE s.img_vignette_id=imgs.img_vignette_id AND vignette_id = %s", GetSQLValueString($exercise,$colname_vignette, "int"));
$vignetteimg = mysqli_query($exercise,$query_vignetteimg);
$row_vignetteimg = mysqli_fetch_assoc($vignetteimg);
$totalRows_vignetteimg = mysqli_num_rows($vignetteimg);

//Displaying Objective for vignette
$query_vig_obj = sprintf("SELECT * FROM vignette_objective vo, objectives o WHERE o.obj_id = vo.obj_id AND vo.vignette_id = %s", GetSQLValueString($exercise,$colname_vignette, "int"));
$vig_obj = mysqli_query($exercise,$query_vig_obj) or die(mysqli_error());
$row_vig_obj = mysqli_fetch_assoc($vig_obj);
$totalRows_vig_obj = mysqli_num_rows($vig_obj);

//Displaying Parent Event
$query_event = sprintf("SELECT e.event_name, e.event_id FROM event e, vignette s WHERE e.event_id=s.event_id AND s.vignette_id = %s", GetSQLValueString($exercise,$colname_vignette, "int"));
$event = mysqli_query($exercise,$query_event);
$row_event = mysqli_fetch_assoc($event);
$totalRows_event = mysqli_num_rows($event);
$eventno = $row_event['event_id'];

//Displaying Key terrain list on a table
$query_vignetteterrain = sprintf("SELECT s.vignette_id, s.vignette_name, skt.vignette_terrain_id, skt.network FROM vignette s, vignette_terrain skt WHERE s.vignette_id = $vignetteid AND s.vignette_id=skt.vignette_id");
$vignetteterrain= mysqli_query($exercise, $query_vignetteterrain);
$row_vignetteterrain = mysqli_fetch_assoc($vignetteterrain);
$totalRows_vignetteterrain = mysqli_num_rows($vignetteterrain);

//Displaying player product list on a table
$query_player_products = sprintf("SELECT * from player_product WHERE vignette_id = $vignetteid");
$player_products= mysqli_query($exercise, $query_player_products);
$row_player_products = mysqli_fetch_assoc($player_products);
$totalRows_player_products = mysqli_num_rows($player_products);

//Displaying Vulnerability list
mysqli_select_db($exmato, $database_exmato);
$query_vulnerability = sprintf("SELECT * from ansible_playbook");
$vulnerability= mysqli_query($exmato, $query_vulnerability);
$row_vulnerability = mysqli_fetch_assoc($vulnerability);
$totalRows_vulnerability = mysqli_num_rows($vulnerability);

//Displaying distinct network name
$query_target_kt = sprintf("SELECT DISTINCT (st.network) FROM vignette s, vignette_terrain st, key_terrain kt WHERE s.vignette_id = $vignetteid AND s.vignette_id=st.vignette_id AND st.network=kt.network");
$target_kt= mysqli_query($exercise, $query_target_kt);
$row_target_kt = mysqli_fetch_assoc($target_kt);
$totalRows_target_kt= mysqli_num_rows($target_kt);

//Displaying key terrain IP address
$query_target_kt1 = sprintf("SELECT s.vignette_id, st.network, kt.network, kt.iskeyterrain, kt.ip, kt.mip FROM vignette s, vignette_terrain st, key_terrain kt WHERE s.vignette_id = $vignetteid AND s.vignette_id=st.vignette_id AND st.network=kt.network AND kt.iskeyterrain='y'");
$target_kt1= mysqli_query($exercise, $query_target_kt1);
$row_target_kt1 = mysqli_fetch_assoc($target_kt1);
$totalRows_target_kt1= mysqli_num_rows($target_kt1);

//Displaying distinct network name
$query_target_kt2 = sprintf("SELECT DISTINCT (st.network) FROM vignette s, vignette_terrain st, key_terrain kt WHERE s.vignette_id = $vignetteid AND s.vignette_id=st.vignette_id AND st.network=kt.network");
$target_kt2= mysqli_query($exercise, $query_target_kt2);
$row_target_kt2 = mysqli_fetch_assoc($target_kt2);
$totalRows_target_kt2= mysqli_num_rows($target_kt2);

//Displaying key terrain IP address
$query_target_kt3 = sprintf("SELECT s.vignette_id, st.network, kt.network, kt.iskeyterrain, kt.ip FROM vignette s, vignette_terrain st, key_terrain kt WHERE s.vignette_id = $vignetteid AND s.vignette_id=st.vignette_id AND st.network=kt.network AND kt.iskeyterrain='y'");
$target_kt3= mysqli_query($exercise, $query_target_kt3);
$row_target_kt3 = mysqli_fetch_assoc($target_kt3);
$totalRows_target_kt3= mysqli_num_rows($target_kt3);

$exname = $exercisedetail['slug'];
//Displaying vulnify table
$query_vulnify = sprintf("SELECT * FROM exmato.ansible_playbook, `$exname`.vulnify WHERE exmato.ansible_playbook.ap_id=`$exname`.vulnify.ap_id AND `$exname`.vulnify.vignette_id =$vignetteid AND status='1' ");
$vulnify= mysqli_query($exercise, $query_vulnify);
$row_vulnify = mysqli_fetch_assoc($vulnify);
$totalRows_vulnify= mysqli_num_rows($vulnify);

//Displaying vulnify table with status 1
$query_vulnify1 = sprintf("SELECT * FROM exmato.ansible_playbook, `$exname`.vulnify WHERE exmato.ansible_playbook.ap_id=`$exname`.vulnify.ap_id AND `$exname`.vulnify.vignette_id =$vignetteid AND status='0' ");
$vulnify1= mysqli_query($exercise, $query_vulnify1);
$row_vulnify1 = mysqli_fetch_assoc($vulnify1);
$totalRows_vulnify1= mysqli_num_rows($vulnify1);

//Populating Inject drop down

$query_vignetteinj = sprintf("SELECT item_id, item_name FROM items WHERE item_category='Inject' ORDER BY item_dtg");
$vignetteinj = mysqli_query($exercise, $query_vignetteinj) or die(mysqli_error());
$row_vignetteinj = mysqli_fetch_assoc($vignetteinj);
$totalRows_vignetteinj = mysqli_num_rows($vignetteinj);

//Populating Artefact drop down

$query_vignetteartefact = sprintf("SELECT item_id, item_name FROM items WHERE item_category='Artefact' ORDER BY item_dtg");
$vignetteartefact = mysqli_query($exercise, $query_vignetteartefact) or die(mysqli_error());
$row_vignetteartefact = mysqli_fetch_assoc($vignetteartefact);
$totalRows_vignetteartefact = mysqli_num_rows($vignetteartefact);

//Populating Activity drop down

$query_vignetteact = sprintf("SELECT item_id, item_name FROM items WHERE item_category='Activity' ORDER BY item_dtg");
$vignetteact = mysqli_query($exercise, $query_vignetteact) or die(mysqli_error());
$row_vignetteact = mysqli_fetch_assoc($vignetteact);
$totalRows_vignetteact = mysqli_num_rows($vignetteact);

//Displaying Activity list on a table
$query_vignetteact2 = "SELECT i.item_id, i.item_name, i.item_description, i.item_dtg, i.item_category FROM  vignette_items vi , items i WHERE vi.vignette_id = $vignetteid AND vi.item_id=i.item_id AND i.item_category = 'activity' ORDER BY i.item_dtg ASC";
$vignette_vignetteact2 = mysqli_query($exercise,$query_vignetteact2);
$row_vignetteact2 = mysqli_fetch_assoc($vignette_vignetteact2);
$totalRows_vignetteact2= mysqli_num_rows($vignette_vignetteact2);

//Populating Participants info drop down

$query_vignetteparticipants = sprintf("SELECT pa_id, pa_name FROM participants");
$vignetteparticipants = mysqli_query($exercise, $query_vignetteparticipants) or die(mysqli_error());
$row_vignetteparticipants = mysqli_fetch_assoc($vignetteparticipants);
$totalRows_vignetteparticipants = mysqli_num_rows($vignetteparticipants);

//Populating Persona Group info drop down
$query_vignettepersonagroup = sprintf("SELECT pg.group_id, pg.group_name FROM exercise e, persona_exercise_link pel, personas p, persona_group_link pgl, persona_groups pg WHERE e.exercise_name='$exercise_name' AND e.exercise_id=pel.exercise_id AND pel.pers_id=p.pers_id AND p.pers_id=pgl.pers_id AND pgl.group_id=pg.group_id");
$vignettepersonagroup = mysqli_query($exmato, $query_vignettepersonagroup);
$row_vignettepersonagroup = mysqli_fetch_assoc($vignettepersonagroup);
$totalRows_vignettepersonagroup = mysqli_num_rows($vignettepersonagroup);

//Populating key terrain name info drop down

$query_keyterrain = sprintf("SELECT DISTINCT (network) FROM key_terrain WHERE iskeyterrain = 'y'");
$keyterrain = mysqli_query($exercise, $query_keyterrain) or die(mysqli_error());
$row_keyterrain = mysqli_fetch_assoc($keyterrain);
$totalRows_keyterrain = mysqli_num_rows($keyterrain);

//Populate item type on dropdown
$query_itemtype = sprintf("SELECT * FROM type_item");
$itemtype = mysqli_query($exercise,$query_itemtype) or die(mysqli_error());
$row_itemtype = mysqli_fetch_assoc($itemtype);
$totalRows_itemtype = mysqli_num_rows($itemtype);

//Excon log list
$query_exconlog = sprintf("SELECT * FROM exconlog WHERE evt_id = $eventno AND vgt_id = $vignetteid");
$exconlog = mysqli_query($exercise,$query_exconlog);
$row_exconlog = mysqli_fetch_assoc($exconlog);
$totalRows_exconlog = mysqli_num_rows($exconlog);

mysqli_select_db($exmanews,$database_exmanews);
$query_exmimg = sprintf("SELECT * FROM images ORDER BY imgname");
$exmimg = mysqli_query($exmanews,$query_exmimg);
$row_exmimg = mysqli_fetch_assoc($exmimg);
$totalRows_exmimg = mysqli_num_rows($exmimg);

//Populating objective drop down
$query_vigobj = sprintf("SELECT obj_id, obj_title, obj_type FROM objectives WHERE obj_type='training' ORDER BY obj_title ASC");
$vigobj = mysqli_query($exercise, $query_vigobj) or die(mysqli_error());
$row_vigobj = mysqli_fetch_assoc($vigobj);
$totalRows_vigobj = mysqli_num_rows($vigobj);

//Displaying Objective for vignette
$query_vig_obj = sprintf("SELECT * FROM vignette_objective vo, objectives o WHERE o.obj_id = vo.obj_id AND vo.vignette_id = %s", GetSQLValueString($exercise,$vignetteid, "int"));
$vig_obj = mysqli_query($exercise,$query_vig_obj) or die(mysqli_error());
$row_vig_obj = mysqli_fetch_assoc($vig_obj);
$totalRows_vig_obj = mysqli_num_rows($vig_obj);


$exe_name = $exercisedetail['slug'];
//Displaying Persona group for vignette
$query_per_group = sprintf("SELECT * FROM `$exe_name`.vignette_persona_group vpg, exmato.persona_groups epg WHERE `$exe_name`.vpg.vignette_id = $vignetteid AND `$exe_name`.vpg.group_id=exmato.epg.group_id");
$per_group = mysqli_query($exercise,$query_per_group);
$row_per_group = mysqli_fetch_assoc($per_group);
$totalRows_per_group = mysqli_num_rows($per_group);
?>
<!doctype html>
<html lang="en" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="ie=edge">

<link rel="icon" href="favicon.ico" type="image/x-icon"/>

<title>EXMATO</title>

<!-- Bootstrap -->
<link rel="stylesheet" href="../assets/plugins/bootstrap/css/bootstrap.min.css" />
<link rel="stylesheet" href="../assets/plugins/dropify/css/dropify.min.css">
<!-- Core css -->
<link rel="stylesheet" href="../assets/css/main.css"/>
<link rel="stylesheet" href="../assets/css/theme1.css"/>

<!-- datetimepicker -->
<link href="../css/bootstrap-datetimepicker.min.css" rel="stylesheet" media="screen">
<script type="text/javascript" src="../jquery/jquery-3.5.0.min.js" charset="UTF-8"></script>
<script type="text/javascript" src="../js/bootstrap-datetimepicker.js" charset="UTF-8"></script>

<!-- flowchart stuff -->
<link href="https://fonts.googleapis.com/css?family=Roboto:400,500,700&display=swap" rel="stylesheet">
<link href='styles.css' rel='stylesheet' type='text/css'>
<link href='flowy.css' rel='stylesheet' type='text/css'>
<script src="flowy.js"></script>
<script src="main.js"></script>
</head>

<body class="font-montserrat sidebar_dark">
<!-- Page Loader -->
<div class="page-loader-wrapper">
     <div class="loader">
     </div>
</div>

<div id="main_content">
     <?php include('../includes/notifications.php'); ?>
     <?php include('../includes/sidebar.php'); ?>
     <?= getMessages() ?>
     <div class="page">
          <div class="section-body mt-3">
               <div class="container-fluid">
                    <div class="tab-content">
                         <div class="row">
                              <div class="col-lg-8 col-md-12">
                                   <?php if ($totalRows_event > 0) { // Show if recordset not empty ?>
                                        <div class="card bg-primary">
                                             <div class="card-body">
                                                  <div class="media mb-4">
                                                       <div class="media-body">
                                                            <h6><span class="btn btn-dark btn-lg"><strong>Trigger Event</strong></span>&nbsp;&nbsp;
                                                                 <a href="excon-event-detail.php?event_id=<?php echo cy2_crypt($row_event['event_id'],'e');?>">
                                                                 <font color="white"><b><?php echo $row_event['event_name'];?> </b></font>
                                                                 </a>
                                                            </h6>
                                                       </div>
                                                  </div>
                                             </div>
                                        </div>
                                   <?php } // Show if recordset not empty ?>

                                        <div class="card">
                                             <div class="card-status card-status-left bg-danger"></div>
                                             <div class="card-body">
                                                  <div class="media mb-4">
                                                       <div class="media-body ">
                                                            <div class="card-options float-right">
                                                                 <div class="item-action dropdown ml-2">
                                                                      <a href="javascript:void(0)" data-toggle="dropdown"><i class="fe fe-more-vertical"></i></a>
                                                                      <div class="dropdown-menu dropdown-menu-right">
                                                                           <a href="javascript:void(0)" data-toggle="modal" data-target="#assignkt" data-backdrop="true" class="dropdown-item"><i class="dropdown-icon fa fa-television mr-2"></i> Assign Key Terrain </a>
                                                                           <a href="javascript:void(0)" data-toggle="modal" data-target="#assignta" data-backdrop="true" class="dropdown-item"><i class="dropdown-icon fe fe-user-plus mr-2"></i> Assign Training Audience </a>
                                                                           <a href="javascript:void(0)" data-toggle="modal" data-target="#assignper" data-backdrop="true" class="dropdown-item"><i class="dropdown-icon fe fe-user-plus mr-2"></i> Assign Persona </a>
                                                                           <div class="dropdown-divider"></div>
                                                                           <a href="edit-vignette.php?vignette_id=<?php echo cy2_crypt($row_vignette['vignette_id'],'e');?>" class="dropdown-item"><i class="dropdown-icon fa fa-edit"></i> Edit Vignette </a>
                                                                           <a href="javascript:void(0)" data-toggle="modal" data-target="#newlessonidentified" data-backdrop="true" class="dropdown-item"><i class="dropdown-icon fa fa-paste"></i> Lesson Identified </a>
                                                                           <a href="javascript:void(0)" data-toggle="modal" data-target="#uploadproduct" data-backdrop="true" class="dropdown-item"><i class="dropdown-icon fa fa-upload"></i> Upload Player Product </a>
                                                                           <div class="dropdown-divider"></div>
                                                                          <?php if ($eventno != null) { ?>
                                                                           <a href="javascript:void(0)" data-toggle="modal" data-target="#exconlog" class="dropdown-item"><i class="dropdown-icon fa fa-calendar-check-o"></i> To-Do List </a>
                                                                          <?php } else { ?>
                                                                           <p class="dropdown-item disabled" style="opacity:50%"><i class="dropdown-icon fa fa-calendar-check-o"></i> To-Do List (Assign First)</p>
                                                                          <?php } ?>
                                                                           <div class="dropdown-divider"></div>
                                                                           <a class="dropdown-item">
                                                                           <form method="POST" action="<?php echo $editFormAction; ?>" name="unlinkall" id="unlinkall">
                                                                                <input type="hidden" name="vignette_id" value="<?php echo cy2_crypt($row_vignette['vignette_id'],'e');?>">
                                                                                <input type="hidden" name="update" value="unlinkall">
                                                                                <button type="submit" class="btn btn-outline-danger" onclick="return unlinkrecord()">
                                                                                     <i></i>Unlink All Objects
                                                                                </button>&nbsp;&nbsp;
                                                                           </form>
                                                                           </a>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <h5 class="m-0">
                                                                 <span class="btn btn-warning btn-lg"><strong>Vignette Name</strong></span>&nbsp;&nbsp;<b><?php echo $row_vignette['vignette_name']; ?></b>
                                                            </h5><p><p>
                                                            <div class="float-right">
                                                                 <form action="<?php echo $editFormAction; ?>" id="whitecardstatus" name="whitecardstatus" method="POST">
                                                                      <?php
                                                                      $whitecardstatus = $row_vignette['whitecard'];
                                                                      if ($whitecardstatus == "yes"){
                                                                           $value = "";
                                                                           $dvalue ="Whitecard On";
                                                                           $tab_stat = "checked";
                                                                           $labelcolor = "tag tag-gray-dark";
                                                                           $fontcolor ="color:white;";
                                                                      }
                                                                      elseif ($whitecardstatus == ""){
                                                                           $value = "yes";
                                                                           $dvalue ="Enable Whitecard";
                                                                           $tab_stat = "";
                                                                           $labelcolor = "tag tag-white";
                                                                           $fontcolor ="color:black;";
                                                                      }
                                                                      ?>
                                                                      <label class="custom-switch" align="right">
                                                                           <input type="hidden" name="vignette_id" value="<?php echo cy2_crypt($row_vignette['vignette_id'],'e');?>">
                                                                           <input type="hidden" name="whitecard" value="<?php echo $value;?>">
                                                                           <input type="checkbox" name="custom-switch-checkbox" class="custom-switch-input" value="<?php echo $value;?>" <?php echo $tab_stat;?> onChange='submit();'>
                                                                           <span class="custom-switch-indicator"></span>
                                                                           <span class="custom-switch-description"></span>
                                                                           <span align="right" class="<?php echo $labelcolor;?>"><b style="<?php echo $fontcolor;?>"><?php echo $dvalue;?></b></span>
                                                                      </label>
                                                                      <input type="hidden" name="vignettestatus"  id="vignettestatus" value="<?php echo $row_vignette['vignette_status'];?>">
                                                                      <input type="hidden" name="dtg"  id="dtg" value="<?php date_default_timezone_set("Europe/London");$timestamp=time(); echo (date("F d, Y h:i:s A", $timestamp));?>">
                                                                      <input type="hidden" name="livefeed"  id="livefeed" value="1">
                                                                      <input type="hidden" name="update" value="whitecardstatus">
                                                                 </form>
                                                            </div>
                                                            <small>Start: <b><?php $startdate=new DateTime($eventsd); echo date_format($startdate, 'H:i'); ?></b> <?php $startdate=new DateTime($eventsd); echo date_format($startdate, 'd F Y'); ?></small><p>
                                                            <small>Finish: <b><?php $finishdate=new DateTime($eventfd); echo date_format($finishdate, 'H:i');?></b> <?php $finishdate=new DateTime($eventfd); echo date_format($finishdate, 'd F Y ');; ?></small>
                                                            <?php if (! empty($video)) { ?>
                                                                 <br>
                                                                 <span align="center">
                                                                 <button type="button" class="btn btn-outline-primary float-right" data-toggle="modal" data-target="#video" data-backdrop="static">
                                                                      <i class="fa fa-video-camera mr-2"></i>Video
                                                                 </button></span>
                                                            <?php };?>
                                                       </div>
                                                  </div>
                                                  <!--<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#geolocation">
                                                  <i class="fa fa-map-marker mr-2"></i>Geolocation
                                                  </button><br><br>-->
                                                  <figure>
                                                       <img src="<?php echo $row_vignetteimg['img_location'];?>" alt="" class="img-thumbnail rounded">
                                                  </figure>
                                                  <p class="mb-4"><?php echo nl2br($row_vignette['vignette_description']); ?></p>
                                                 <hr>
                                                 <h5>Vignette Items</h5>
                                                 <div>
                                                     <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#flow-canvas">Flow Canvas</button>
                                                 </div>
                                             </div>
                                        </div>

                                        <div class="card" style="background-color:#0e2f44; color:white">
                                             <div class="card-body">
                                                  <div class="media mb-4">
                                                       <div class="media-body">
                                                            <div class="card-options float-right">
                                                                 <div class="item-action dropdown ml-2">
                                                                      <a href="javascript:void(0)" data-toggle="dropdown"><i class="fe fe-more-vertical" style="color:white"></i></a>
                                                                      <div class="dropdown-menu dropdown-menu-right">
                                                                           <a href="javascript:void(0)" data-toggle="modal" data-target="#assignobj" data-backdrop="true" class="dropdown-item"><i class="dropdown-icon fa fa-check-square-o"></i> Assign Objectives </a>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <h6 class="m-0">Vignette Training Objectives</h6>
                                                       </div>
                                                  </div>
                                                  <?php if ($totalRows_vig_obj > 0) { // Show if recordset not empty ?>
                                                       <div class="mb-4">
                                                            <div class="media-body">
                                                                 <?php do { ?>
                                                                      <form method="POST" action="<?php echo $editFormAction; ?>" name="removeobj" id="removeobj">
                                                                           <span class="tag tag-yellow mb-3" style="color:black"><?php echo $row_vig_obj['obj_title']; ?></span>
                                                                           <button class="btn btn-icon btn-sm" title="Remove" data-toggle="tooltip" data-placement="top"><i class="icon-trash text-red"></i></button>
                                                                           <input type="hidden" name="vo_id" value="<?php echo cy2_crypt($row_vig_obj['vo_id'],'e');?>">
                                                                           </button>
                                                                      </form>
                                                                 <?php } while ($row_vig_obj = mysqli_fetch_assoc($vig_obj)); ?>
                                                            </div>
                                                       </div>
                                                  <?php } // Show if recordset not empty ?>
                                             </div>
                                        </div>


                                        <?php if ($totalRows_vignetteact2 > 0) { // Show if recordset not empty ?>
                                        <div class="card" style="background-color:#ffffff; color:black">
                                             <div class="card-header">
                                                  <h3 class="card-title"><h6 class="m-0">Activity List</h6><br></h3>
                                                  <div class="card-options">
                                                       <a href="#" class="card-options-collapse" data-toggle="card-collapse" style="color:black"><i class="fe fe-chevron-up"></i></a>
                                                       <a href="#" class="card-options-remove" data-toggle="card-remove" style="color:black"><i class="fe fe-x"></i></a>
                                                  </div>
                                             </div>
                                        <!-- Activity List card -->
                                        <div class="card-body">
                                             <div class="table">
                                                  <table class="table table-hover text-nowrap mb-0">
                                                       <tbody>
                                                            <tr class="thead-dark">
                                                                 <th>Activity Name</th>
                                                                 <th class="w200">DTG</th>
                                                            </tr>
                                                            <?php do { ?>
                                                                 <tr style="background-color:#a6a6a6; color:black">
                                                                      <td>
                                                                           <a href="activity-admin.php?item_id=<?php echo cy2_crypt($row_vignetteact2['item_id'],'e');?>" style="color:black"><b><?php echo $row_vignetteact2['item_name']; ?></b></a>
                                                                      </td>
                                                                      <td>
                                                                           <b><?php $dtgstamp=new DateTime($row_vignetteact2['item_dtg']); echo date_format($dtgstamp, 'H:i');?>,</b> <?php $dtgstamp=new DateTime($row_vignetteact2['item_dtg']); echo date_format($dtgstamp, 'M d, Y');?>
                                                                      </td>
                                                                 </tr>
                                                            <?php } while ($row_vignetteact2 = mysqli_fetch_assoc($vignette_vignetteact2)); ?>
                                                       </tbody>
                                                  </table>
                                             </div>
                                        </div>
                                   </div>
                                  <?php } // Show if recordset not empty ?>

                                        <?php if ($totalRows_vignette_artefacts > 0) { // Show if recordset not empty ?>
                                        <div class="card" style="background-color:#ffffff; color:black">
                                             <div class="card-header">
                                                  <h3 class="card-title"><h6 class="m-0">Artefact List</h6><br></h3>
                                                  <div class="card-options">
                                                       <a href="#" class="card-options-collapse" data-toggle="card-collapse" style="color:black"><i class="fe fe-chevron-up"></i></a>
                                                       <a href="#" class="card-options-remove" data-toggle="card-remove" style="color:black"><i class="fe fe-x"></i></a>
                                                  </div>
                                             </div>
                                             <!-- Artefact List card -->
                                             <div class="card-body">
                                                  <div class="table">
                                                       <table class="table table-hover text-nowrap mb-0">
                                                            <tbody>
                                                                 <tr class="thead-dark">
                                                                      <th>Artefact Name</th>
                                                                      <th class="w200">Type</th>
                                                                      <th class="w200">DTG</th>
                                                                 </tr>
                                                                 <?php do { ?>
                                                                      <tr style="background-color:#ffd6cc; color:black">
                                                                           <td>
                                                                                <a href="artefact-admin.php?item_id=<?php echo cy2_crypt($row_vignette_artefacts['item_id'],'e');?>" style="color:black"><b><?php echo $row_vignette_artefacts['item_name']; ?></b></a>
                                                                           </td>
                                                                           <td><i class="<?php echo $row_vignette_artefacts['icon'];?> fa-lg"> </i> <span class="tag tag-<?php echo $row_vignette_artefacts['colour'];?>"><?php echo $row_vignette_artefacts['type'];?></span></td>
                                                                           <td>
                                                                                <b><?php $dtgstamp=new DateTime($row_vignette_artefacts['item_dtg']); echo date_format($dtgstamp, 'H:i');?>,</b> <?php $dtgstamp=new DateTime($row_vignette_artefacts['item_dtg']); echo date_format($dtgstamp, 'M d, Y');?>
                                                                           </td>
                                                                      </tr>
                                                                 <?php } while ($row_vignette_artefacts = mysqli_fetch_assoc($vignette_artefacts)); ?>
                                                            </tbody>
                                                       </table>
                                                  </div>
                                             </div>
                                        </div>
                                        <?php } // Show if recordset not empty ?>

                                        <?php if ($totalRows_vignette_injects > 0) { // Show if recordset not empty ?>
                                        <div class="card" style="background-color:#ffffff; color:black">
                                             <div class="card-header">
                                                  <h3 class="card-title"><h6 class="m-0">Inject List</h6><br></h3>
                                                  <div class="card-options">
                                                       <a href="#" class="card-options-collapse" data-toggle="card-collapse" style="color:black"><i class="fe fe-chevron-up"></i></a>
                                                       <a href="#" class="card-options-remove" data-toggle="card-remove" style="color:black"><i class="fe fe-x"></i></a>
                                                  </div>
                                             </div>
                                             <div class="card-body">
                                                  <div class="table">
                                                       <table class="table table-hover text-nowrap mb-0">
                                                            <tbody>
                                                                 <tr class="thead-dark">
                                                                      <th>Inject Name</th>
                                                                      <th class="w200">Type</th>
                                                                      <th class="w200">DTG</th>
                                                                 </tr>
                                                                 <?php do { ?>
                                                                      <tr style="background-color:#ccf2ff; color:black">
                                                                           <td><a href="inject-admin.php?item_id=<?php echo cy2_crypt($row_vignette_injects['item_id'],'e');?>" style="color:black"><b><?php echo $row_vignette_injects['item_name']; ?></b></a></td>
                                                                           <td><i class="<?php echo $row_vignette_injects['icon'];?> fa-lg"> </i> <span class="tag tag-<?php echo $row_vignette_injects['colour'];?>"><?php echo $row_vignette_injects['type'];?>  </span> </td>
                                                                           <td><b><?php $dtgstamp=new DateTime($row_vignette_injects['inj_dtg']); echo date_format($dtgstamp, 'H:i');?>, </b> <?php $dtgstamp=new DateTime($row_vignette_injects['inj_dtg']); echo date_format($dtgstamp, 'M d, Y');?></td>
                                                                           <input type="hidden" name="item_id" value="<?php echo cy2_crypt($row_vignette_injects['item_id'],'e');?>">
                                                                      </tr>
                                                                 <?php } while ($row_vignette_injects = mysqli_fetch_assoc($vignette_injects)); ?>
                                                            </tbody>
                                                       </table>
                                                  </div>
                                             </div>
                                        </div>
                                        <?php } // Show if recordset not empty ?>

                                        <?php if ($totalRows_vignette_email1 > 0) { // Show if recordset not empty ?>
                                        <?php if ($colname_exercise_type != 'Table Top Exercise (TTX)' and $colname_exercise_type != 'Command Post Exercise (CPX)'){?>
                                        <div class="card" style="background-color:#0e2f44; color:white">
                                             <div class="card-header">
                                                  <h3 class="card-title"><h6 class="m-0">Email Status List</h6><br></h3>
                                                  <div class="card-options">
                                                       <a href="#" class="card-options-collapse" data-toggle="card-collapse" style="color:white"><i class="fe fe-chevron-up"></i></a>
                                                       <a href="#" class="card-options-remove" data-toggle="card-remove" style="color:white"><i class="fe fe-x"></i></a>
                                                  </div>
                                             </div>

                                             <div class="card-header"><a class="btn btn-primary" data-toggle="collapse" href="#sendemail" role="button" aria-expanded="false" aria-controls="collapseExample"><i class="fa fa-envelope mr-2"></i>Email Status List</a></div>
                                                  <div class="collapse" id="sendemail">
                                                       <div class="card-body">
                                                            <div class="table-responsive">
                                                                 <table class="table text-nowrap mb-0">
                                                                      <tbody>
                                                                           <tr class="thead-dark">
                                                                                <th class="w200">Sender</th>
                                                                                <th class="w200">Recipient</th>
                                                                                <th>subject</th>
                                                                                <th class="w100">action</th>
                                                                           </tr>
                                                                           <?php do { ?>
                                                                                <?php
                                                                                $emailstatus = $row_vignette_email1['email_status'];
                                                                                if ($emailstatus == "Unsent"){
                                                                                     $labelcolor = "tag tag-red";
                                                                                }
                                                                                elseif ($emailstatus == "Sent"){
                                                                                     $labelcolor = "tag tag-gray";
                                                                                }
                                                                                else {
                                                                                     $labelcolor = "tag tag-lime";
                                                                                }
                                                                                ?>
                                                                                <form method="POST" action="<?php echo $editFormAction; ?>" name="stopemail" id="stopemail">
                                                                                     <tr class="alert alert-danger">
                                                                                          <td><small><span><b> <?php echo $row_vignette_email1['sender'];?> </b></small></span></td>
                                                                                          <td><small><span> <?php echo $row_vignette_email1['recipient'];?></small></span></td>
                                                                                          <td><small><span> <?php echo $row_vignette_email1['subject'];?></small></span></td>
                                                                                          <input type="hidden" name="vignette_id" value="<?php echo cy2_crypt($row_vignette_email['vignette_id'],'e');?>">
                                                                                          <td> <button class="btn btn-icon btn-sm" title="Abort" data-toggle="tooltip" data-placement="top"><i class="fa fa-minus-circle text-red"></i></button></td>
                                                                                     </tr>
                                                                                     <input type="hidden" name="email_id" value="<?php echo cy2_crypt($row_vignette_email1['email_id'],'e');?>">
                                                                                     <input type="hidden" name="delete" value="stopemail">
                                                                                </form>
                                                                           <?php } while ($row_vignette_email1 = mysqli_fetch_assoc($vignette_email1)); ?>
                                                                      </tbody>
                                                                 </table>
                                                            </div>
                                                       </div>
                                                  </div>

                                                  <div class="card-body">
                                                       <div class="table-responsive">
                                                            <?php if ($totalRows_vignette_email > 0) { // Show if recordset not empty ?>
                                                                 <table class="table text-nowrap mb-0">
                                                                      <tbody>
                                                                           <tr class="thead-dark">
                                                                                <th class="w200">Sender</th>
                                                                                <th class="w200">Recipient</th>
                                                                                <th>Subject</th>
                                                                                <th class="w100">Status</th>
                                                                           </tr>
                                                                           <?php do { ?>
                                                                                <?php
                                                                                $emailstatus = $row_vignette_email['email_status'];
                                                                                if ($emailstatus == "Unsent"){
                                                                                     $labelcolor = "tag tag-red";
                                                                                }
                                                                                elseif ($emailstatus == "Sent"){
                                                                                     $labelcolor = "tag tag-blue";
                                                                                }
                                                                                else {
                                                                                     $labelcolor = "tag tag-gray";
                                                                                }
                                                                                ?>
                                                                                <tr class="alert alert-info">
                                                                                     <td><small><span><b> <?php echo $row_vignette_email['sender'];?></b></small></span></td>
                                                                                     <td><small><span> <?php echo $row_vignette_email['recipient'];?></small></span></td>
                                                                                     <td><small><span> <?php echo $row_vignette_email['subject'];?></small></span></td>
                                                                                     <td><small><span class="<?php echo $labelcolor;?>"> <?php echo $row_vignette_email['email_status'];?></small></span></td>
                                                                                </tr>
                                                                           <?php } while ($row_vignette_email = mysqli_fetch_assoc($vignette_email)); ?>
                                                                      </tbody>
                                                                 </table>
                                                            <?php } // Show if recordset not empty ?>
                                                       </div>
                                                  </div>
                                             </div>
                                        <?php } ?>
                                        <?php } // Show if recordset not empty ?>

                                   <?php if ($colname_exercise_type != 'Table Top Exercise (TTX)' and $colname_exercise_type != 'Command Post Exercise (CPX)'){?>
                                        <?php if ($totalRows_vulnify > 0) { // Show if recordset not empty ?>
                                        <div class="card" style="background-color:#0e2f44; color:white">
                                             <div class="card-header">
                                                  <h3 class="card-title"><h6 class="m-0">Vulnify List</h6><br></h3>
                                                  <div class="card-options">
                                                       <a href="#" class="card-options-collapse" data-toggle="card-collapse" style="color:white"><i class="fe fe-chevron-up"></i></a>
                                                       <a href="#" class="card-options-remove" data-toggle="card-remove" style="color:white"><i class="fe fe-x"></i></a>
                                                  </div>
                                             </div>
                                             <div class="card-header"><a class="btn btn-primary" data-toggle="collapse" href="#vulnifyexpand" role="button" aria-expanded="false" aria-controls="collapseExample"><i class="fa fa-wrench mr-2"></i>Vulnify Status</a></div>
                                             <div class="collapse" id="vulnifyexpand">
                                             <div class="card-body">
                                                  <div class="table-responsive">
                                                       <table class="table text-nowrap mb-0">
                                                            <tbody>
                                                                 <tr class="thead-dark">
                                                                      <th>Vulnerability Type</th>
                                                                      <th class="w200">Network Name</th>
                                                                      <th class="w200">IP Address</th>
                                                                      <th class="w100">action</th>
                                                                 </tr>
                                                                 <?php do { ?>
                                                                      <?php
                                                                      $status = $row_vulnify['status'];
                                                                      if ($status == "1"){
                                                                           $value = "Scheduled";
                                                                           $labelcolor = "tag tag-red";
                                                                      }
                                                                      elseif ($status == "0"){
                                                                           $value = "Completed";
                                                                           $labelcolor = "tag tag-gray";
                                                                      }
                                                                      else {
                                                                           $labelcolor = "tag tag-lime";
                                                                      }
                                                                      ?>
                                                                      <form method="POST" action="<?php echo $editFormAction; ?>" name="stopvulnify" id="stopvulnify">
                                                                           <tr class="alert alert-danger">
                                                                                <td><small><span><b> <?php echo $row_vulnify['ap_name'];?></b></small></span></td>
                                                                                <td><small><span> <?php echo $row_vulnify['network'];?></small></span></td>
                                                                                <td><small><span> <?php echo $row_vulnify['target'];?></small></span></td>
                                                                                <td> <button class="btn btn-icon btn-sm" title="Abort" data-toggle="tooltip" data-placement="top"><i class="fa fa-minus-circle text-red"></i></button></td>
                                                                           </tr>
                                                                           <input type="hidden" name="vulnify_id" value="<?php echo cy2_crypt($row_vulnify['vulnify_id'],'e');?>">
                                                                           <input type="hidden" name="delete" value="stopvulnify">
                                                                      </form>
                                                                 <?php } while ($row_vulnify = mysqli_fetch_assoc($vulnify)); ?>
                                                            </tbody>
                                                       </table>
                                                  </div>
                                             </div>
                                             </div>


                                             <div class="card-body">
                                                  <div class="table-responsive">
                                                       <?php if ($totalRows_vulnify1 > 0) { // Show if recordset not empty ?>
                                                            <table class="table table-hover text-nowrap mb-0">
                                                                 <tbody>
                                                                      <tr class="thead-dark">
                                                                           <th>Vulnerability Type</th>
                                                                           <th class="w200">Network Name</th>
                                                                           <th class="w200">IP Address</th>
                                                                           <th class="w100">Status</th>
                                                                      </tr>
                                                                      <?php do { ?>
                                                                           <?php
                                                                           $status = $row_vulnify1['status'];
                                                                           if ($status == "1"){
                                                                                $value = "Scheduled";
                                                                                $labelcolor = "tag tag-red";
                                                                           }
                                                                           elseif ($status == "0"){
                                                                                $value = "Completed";
                                                                                $labelcolor = "tag tag-gray";
                                                                           }
                                                                           else {
                                                                                $labelcolor = "tag tag-lime";
                                                                           }
                                                                           ?>
                                                                           <tr class="alert alert-info">
                                                                                <td><small><span><b> <?php echo $row_vulnify1['ap_name'];?></b></small></span></td>
                                                                                <td><small><span> <?php echo $row_vulnify1['network'];?></small></span></td>
                                                                                <td><small><span> <?php echo $row_vulnify1['target'];?></small></span></td>
                                                                                <td><small><span class="<?php echo $labelcolor;?>"> <?php echo $value;?></small></span></td>
                                                                           </tr>
                                                                      <?php } while ($row_vulnify1 = mysqli_fetch_assoc($vulnify1)); ?>
                                                                 </tbody>
                                                            </table>
                                                       <?php } // Show if recordset not empty ?>
                                                  </div>
                                             </div>
                                        </div>
                                        <?php } // Show if recordset not empty ?>
                                   <?php } ?>

                                   <?php if ($totalRows_player_products > 0) { // Show if recordset not empty ?>
                                        <div class="card" style="background-color:#0e2f44; color:white">
                                             <div class="card-header">
                                                  <h3 class="card-title"><h6 class="m-0">Player Products</h6><br></h3>
                                                  <div class="card-options">
                                                       <a href="#" class="card-options-collapse" data-toggle="card-collapse" style="color:white"><i class="fe fe-chevron-up"></i></a>
                                                       <a href="#" class="card-options-remove" data-toggle="card-remove" style="color:white"><i class="fe fe-x"></i></a>
                                                  </div>
                                             </div>
                                             <div class="card-body">
                                                  <div class="table-responsive">
                                                       <table class="table text-nowrap mb-0">
                                                            <tbody>
                                                                 <tr class="thead-dark">
                                                                      <th>Filename</th>
                                                                      <th class="w200">DTG Uploaded</th>
                                                                      <th class="w200">Training Audience</th>
                                                                      <th class="w100">Action</th>
                                                                 </tr>
                                                                 <?php do { ?>
                                                                      <tr class="alert alert-info">
                                                                           <td>
                                                                                <a href="player_products/<?php echo $row_player_products['file'];?>" target="_blank">
                                                                                     <b style="color:black"><?php echo $row_player_products['name']; ?></b>
                                                                                </a>
                                                                           </td>
                                                                           <td>
                                                                                <b><?php $dtgstamp=new DateTime($row_player_products['date_submitted']); echo date_format($dtgstamp, 'H:i');?>,</b> <?php $dtgstamp=new DateTime($row_player_products['date_submitted']); echo date_format($dtgstamp, 'M d, Y');?>
                                                                           </td>
                                                                           <td>
                                                                                <small><span class="tag tag-gray"> <?php echo $row_player_products['training_audience'];?>
                                                                                </small></span>
                                                                           </td>
                                                                           <form method="POST" action="<?php echo $editFormAction; ?>" name="removeproduct" id="removeproduct">
                                                                                <td>
                                                                                     <button class="btn btn-icon btn-sm" title="Remove" data-toggle="tooltip" data-placement="top">
                                                                                          <i class="icon-trash text-red"></i>
                                                                                     </button>
                                                                                     <input type="hidden" name="file" value="<?php echo $row_player_products['file'];?>">
                                                                                     <input type="hidden" name="productid" value="<?php echo cy2_crypt($row_player_products['productid'],'e');?>">
                                                                           </form>
                                                                                </td>
                                                                      </tr>
                                                                 <?php } while ($row_player_products = mysqli_fetch_assoc($player_products)); ?>
                                                            </tbody>
                                                       </table>
                                                  </div>
                                             </div>
                                        </div>
                                   <?php } // Show if recordset not empty ?>
                              </div>

                              <div class="col-lg-4">
                                   <div class="card" style="background-color:#F8F8FF; color:black">
                                        <div class="card-header">
                                             <h3 class="card-title"><h6 class="m-0">Vignette Timeline</h6><br></h3>
                                             <div class="card-options">
                                                  <div class="card-options float-right">
                                                       <div class="item-action dropdown ml-2">
                                                            <a href="javascript:void(0)" data-toggle="dropdown"><i class="fe fe-more-vertical"></i></a>
                                                            <div class="dropdown-menu dropdown-menu-right">
                                                                 <a href="javascript:void(0)" data-toggle="modal" data-target="#uploaditem" data-backdrop="true" class="dropdown-item"><i class="dropdown-icon fa fa-files-o mr-2"></i> Add Artefact </a>
                                                                 <a href="javascript:void(0)" data-toggle="modal" data-target="#uploaditem" data-backdrop="true" class="dropdown-item"><i class="dropdown-icon fe fe-mail mr-2"></i> Add Inject </a>
                                                                 <a href="javascript:void(0)" data-toggle="modal" data-target="#addactivity" data-backdrop="true" class="dropdown-item"><i class="dropdown-icon fa fa-calendar-check-o mr-2"></i> Add Activity </a>
                                                                 <div class="dropdown-divider"></div>
                                                                 <a href="javascript:void(0)" data-toggle="modal" data-target="#assignartefact" data-backdrop="true" class="dropdown-item"><i class="dropdown-icon fa fa-files-o mr-2"></i> Assign Artefact </a>
                                                                 <a href="javascript:void(0)" data-toggle="modal" data-target="#assigninject" data-backdrop="true" class="dropdown-item"><i class="dropdown-icon fe fe-mail mr-2"></i> Assign Inject </a>
                                                                 <a href="javascript:void(0)" data-toggle="modal" data-target="#assignactivity" data-backdrop="true" class="dropdown-item"><i class="dropdown-icon fa fa-calendar-check-o mr-2"></i> Assign Activity </a>
                                                                 <div class="dropdown-divider"></div>
                                                                 <?php if ($colname_exercise_type != 'Table Top Exercise (TTX)' and $colname_exercise_type != 'Command Post Exercise (CPX)'){?>
                                                                 <a href="javascript:void(0)" data-toggle="modal" data-target="#email" data-backdrop="true" class="dropdown-item"><i class="dropdown-icon fa fa-envelope mr-2"></i> Deploy Email </a>
                                                                 <?php } ?>
                                                                 <a href="javascript:void(0)" data-toggle="modal" data-target="#injectnews" data-backdrop="true" class="dropdown-item"><i class="dropdown-icon fa fa-globe mr-2"></i> Deploy News </a>
                                                                 <?php if ($colname_exercise_type != 'Table Top Exercise (TTX)' and $colname_exercise_type != 'Command Post Exercise (CPX)'){?>
                                                                 <div class="dropdown-divider"></div>
                                                                 <a href="javascript:void(0)" data-toggle="modal" data-target="#deployvulnerability" data-backdrop="true" class="dropdown-item"><i class="dropdown-icon fa fa-wrench mr-2"></i> Vulnify </a>
                                                                 <?php } ?>
                                                            </div>
                                                       </div>
                                                  </div>
                                                  <a href="#" class="card-options-collapse" data-toggle="card-collapse"><i class="fe fe-chevron-up"></i></a>
                                                  <a href="#" class="card-options-remove" data-toggle="card-remove"><i class="fe fe-x"></i></a>
                                             </div>
                                        </div>

                                        <div class="card-body">
                                             <?php if ($totalRows_vignette_item1 > 0) { // Show if recordset not empty ?>
                                                  <ul class="new_timeline mt-3">
                                                       <li>
                                                            <div class="bullet blue"></div>
                                                            <div class="time">Start</div><br>
                                                            <?php do { ?>
                                                                 <?php
                                                                 $etid = $row_vignette_item1['item_id'];
                                                                 $query_vignette_item2 = "SELECT item_id, item_category,item_document, type, itemastatus FROM items WHERE item_id = $etid";
                                                                 $vignette_item2 = mysqli_query($exercise,$query_vignette_item2);
                                                                 $row_vignette_item2 = mysqli_fetch_assoc($vignette_item2);
                                                                 $totalRows_vignette_item2 = mysqli_num_rows($vignette_item2);

                                                                 $query_vignette_artstatus = "SELECT i.item_id, pa.status FROM items i, place_artefacts pa WHERE i.item_id = $etid AND i.item_id = pa.item_id AND pa.estype='vignette' AND pa.typeid= $colname_vignette";
                                                                 $vignette_artstatus = mysqli_query($exercise,$query_vignette_artstatus);
                                                                 $row_vignette_artstatus = mysqli_fetch_assoc($vignette_artstatus);
                                                                 $totalRows_vignette_artstatus = mysqli_num_rows($vignette_artstatus);

                                                                 $query_vignette_injstatus = "SELECT i.item_id, ei.mail_status FROM items i, email_injects ei WHERE i.item_id = $etid AND i.item_id = ei.item_id AND ei.estype ='vignette' AND ei.typeid= $colname_vignette";
                                                                 $vignette_injstatus = mysqli_query($exercise,$query_vignette_injstatus);
                                                                 $row_vignette_injstatus = mysqli_fetch_assoc($vignette_injstatus);
                                                                 $totalRows_vignette_injstatus = mysqli_num_rows($vignette_injstatus);

                                                                 $itemtypes = $row_vignette_item2['item_category'];
                                                                 $filedocument = $row_vignette_item2['item_document'];
                                                                 $item_id = cy2_crypt($row_vignette_item2['item_id'],'e');
                                                                 $artstatus = $row_vignette_artstatus['status'];
                                                                 $injstatus = $row_vignette_injstatus['mail_status'];
                                                                 $astatus = $row_vignette_item2['itemastatus'];

                                                                 if ($itemtypes == "Activity"){
                                                                      $value = "fa fa-calendar";
                                                                      $labelcolor = "tag tag-dark";
                                                                      $typecolor = "badge badge-dark";
                                                                      $linkurl = "edit-activity.php?";
                                                                      $fileurl = "activity-admin.php?item_id=$item_id";
                                                                      $statusurl = "activitystatus";
                                                                      $istatus = $astatus;
                                                                 }
                                                                 elseif ($itemtypes == "Artefact"){
                                                                      $value = "fa fa-files-o";
                                                                      $labelcolor = "tag tag-red";
                                                                      $typecolor = "badge badge-danger";
                                                                      $linkurl = "edit-artefact.php?";
                                                                      $fileurl = "master_item_list_docs/$filedocument";
                                                                      $istatus = $artstatus;
                                                                      $hrefurl = "deployartefact";
                                                                      $statusurl = "artefactstatus";
                                                                 }
                                                                 elseif ($itemtypes == "Inject"){
                                                                      $value = "fa fa-envelope-o";
                                                                      $labelcolor = "tag tag-blue";
                                                                      $typecolor = "badge badge-info";
                                                                      $linkurl = "edit-inject.php?";
                                                                      $fileurl = "master_item_list_docs/$filedocument";
                                                                      $istatus = $injstatus;
                                                                      $hrefurl = "injectemail";
                                                                      $statusurl = "injectstatus";
                                                                 }
                                                                 ?>

                                                                 <div class="bullet pink"></div>

                                                                 <?php
                                                                 if ($istatus == 'Deployed' or $istatus == 'Complete'){
                                                                      $deploytag = 'background-color:#F0FFF0';
                                                                      }
                                                                      else {
                                                                           $deploytag = "";
                                                                      }
                                                                      ?>
                                                                 <div class="desc" style="<?php echo $deploytag;?>">
                                                                      <h3><span class="<?php echo $labelcolor;?>"><?php echo $row_vignette_item1['item_category'];?> </span>&nbsp;&nbsp;
                                                                           <?php if ($istatus == 'Deployed' or $istatus == 'Complete'){?>
                                                                           <span class="fa fa-check-circle fa-lg text-green" ></span>
                                                                           <?php };?><br>
                                                                      <form method="POST" action="<?php echo $editFormAction; ?>" name="removeitem" id="removeitem">
                                                                      <i class="<?php echo $value;?> fa-lg"> </i>
                                                                      <a href="<?php echo $fileurl;?>"> <?php echo $row_vignette_item1['item_name']; ?></a>
                                                                      <?php if ($itemtypes != 'Activity'){?>
                                                                      <span class="badge badge-secondary"><?php echo $row_vignette_item1['type'];?> </span>&nbsp;&nbsp;
                                                                      <?php };?>
                                                                      <button type="button" class="btn btn-icon btn-sm" title="Edit" onclick="window.location.href='<?php echo $linkurl;?>item_id=<?php echo cy2_crypt($row_vignette_item1['item_id'],'e');?>'" data-toggle="tooltip" data-placement="top"> <i class="fa fa-edit fa-lg"></i></button>
                                                                      <button class="btn btn-icon btn-sm" title="Remove" data-toggle="tooltip" data-placement="top"><i class="icon-trash text-red"></i></button></h3>
                                                                      <input type="hidden" name="vignette_items_id" value="<?php echo cy2_crypt($row_vignette_item1['vignette_items_id'],'e');?>">
                                                                      </form>

                                                                      <?php if ($itemtypes == 'Inject'){?>
                                                                           <?php
                                                                           $query_injectitem = "SELECT * FROM items i, email_injects ei WHERE i.item_id = $etid AND i.item_id = ei.item_id AND ei.estype='vignette' AND ei.typeid= $colname_vignette ORDER BY i.item_dtg";
                                                                           $injectitem = mysqli_query($exercise,$query_injectitem);
                                                                           $row_injectitem = mysqli_fetch_assoc($injectitem);
                                                                           $totalRows_injectitem = mysqli_num_rows($injectitem);

                                                                           $injectstatus = $row_injectitem['mail_status'];
                                                                           if ($injectstatus == "Deployed"){
                                                                                $ivalue = "Deploying";
                                                                                $idvalue ="Inject Deployed";
                                                                                $itab_stat = "checked";
                                                                                $ilabelcolor = "tag tag-gray-dark";
                                                                                $ifontcolor ="color:white;";
                                                                           }
                                                                           elseif ($injectstatus == "Deploying"){
                                                                                $ivalue = "Deployed";
                                                                                $idvalue ="Mark Deployed";
                                                                                $itab_stat = "";
                                                                                $ilabelcolor = "tag tag-white";
                                                                                $ifontcolor ="color:black;";
                                                                           }
                                                                           ?>
                                                                           <?php if ($totalRows_injectitem > 0) { // Show if recordset not empty ?>
                                                                           <form action="<?php echo $editFormAction; ?>" id="updateinjectstatus" name="updateinjectstatus" method="POST">
                                                                                <label class="custom-switch" align="right">
                                                                                     <input type="hidden" name="item_id" value="<?php echo cy2_crypt($row_injectitem['item_id'],'e'); ?>">
                                                                                     <input type="hidden" name="mail_status" value="<?php echo $ivalue;?>">
                                                                                     <input type="checkbox" name="custom-switch-checkbox" class="custom-switch-input" value="<?php echo $ivalue;?>" <?php echo $itab_stat;?> onChange='submit();'>
                                                                                     <span class="custom-switch-indicator"></span>
                                                                                     <span class="custom-switch-description"></span>
                                                                                     <span align="right" class="<?php echo $ilabelcolor;?>"><b style="<?php echo $ifontcolor;?>"><?php echo $idvalue;?></b></span>
                                                                                     <input type="hidden" name="entry_dtg"  id="entry_dtg" value="@ <?php date_default_timezone_set("Europe/London");$timestamp=time(); echo (date("F d, Y h:i:s A", $timestamp));?>; Updated by: <?php echo $colname_user_profile;?>">
                                                                                </label>
                                                                                <input type="hidden" name="update" value="updateinjectstatus">
                                                                           </form>
                                                                           <?php };?>
                                                                      <?php };?>

                                                                      <?php if ($itemtypes == 'Artefact'){?>
                                                                      <?php
                                                                      $query_artefactitem = "SELECT * FROM items i, place_artefacts pa WHERE i.item_id = $etid AND i.item_id = pa.item_id AND pa.estype='vignette' AND pa.typeid= $colname_vignette ORDER BY i.item_dtg";
                                                                      $artefactitem = mysqli_query($exercise,$query_artefactitem);
                                                                      $row_artefactitem = mysqli_fetch_assoc($artefactitem);
                                                                      $totalRows_artefactitem = mysqli_num_rows($artefactitem);

                                                                      $artefactstatus = $row_artefactitem['status'];
                                                                      if ($artefactstatus == "Deployed"){
                                                                           $ivalue = "Deploying";
                                                                           $idvalue ="Artefact Deployed";
                                                                           $itab_stat = "checked";
                                                                           $ilabelcolor = "tag tag-gray-dark";
                                                                           $ifontcolor ="color:white;";
                                                                      }
                                                                      elseif ($artefactstatus == "Deploying"){
                                                                           $ivalue = "Deployed";
                                                                           $idvalue ="Mark Deployed";
                                                                           $itab_stat = "";
                                                                           $ilabelcolor = "tag tag-white";
                                                                           $ifontcolor ="color:black;";
                                                                      }
                                                                      ?>
                                                                      <?php if ($totalRows_artefactitem > 0) { // Show if recordset not empty ?>
                                                                           <form action="<?php echo $editFormAction; ?>" id="updateartefactstatus" name="updateartefactstatus" method="POST">
                                                                                <label class="custom-switch" align="right">
                                                                                     <input type="hidden" name="item_id" value="<?php echo cy2_crypt($row_artefactitem['item_id'],'e'); ?>">
                                                                                     <input type="hidden" name="status" value="<?php echo $ivalue;?>">
                                                                                     <input type="checkbox" name="custom-switch-checkbox" class="custom-switch-input" value="<?php echo $ivalue;?>" <?php echo $itab_stat;?> onChange='submit();'>
                                                                                     <span class="custom-switch-indicator"></span>
                                                                                     <span class="custom-switch-description"></span>
                                                                                     <span align="right" class="<?php echo $ilabelcolor;?>"><b style="<?php echo $ifontcolor;?>"><?php echo $idvalue;?></b></span>
                                                                                     <input type="hidden" name="entry_dtg"  id="entry_dtg" value="@ <?php date_default_timezone_set("Europe/London");$timestamp=time(); echo (date("F d, Y h:i:s A", $timestamp));?>; Updated by: <?php echo $colname_user_profile;?>">
                                                                                </label>
                                                                                <input type="hidden" name="update" value="updateartefactstatus">
                                                                           </form>
                                                                      <?php };?>
                                                                      <?php };?>

                                                                      <?php if ($itemtypes == 'Activity'){?>
                                                                      <form action="<?php echo $editFormAction; ?>" id="updateactivitystatus" name="updateactivitystatus" method="POST">
                                                                           <?php
                                                                           $activitystatus = $row_vignette_item1['itemastatus'];
                                                                           if ($activitystatus == "Complete"){
                                                                                $ivalue = "Incomplete";
                                                                                $idvalue ="Activity Complete";
                                                                                $itab_stat = "checked";
                                                                                $ilabelcolor = "tag tag-gray-dark";
                                                                                $ifontcolor ="color:white;";
                                                                           }
                                                                           elseif ($activitystatus == "Incomplete"){
                                                                                $ivalue = "Complete";
                                                                                $idvalue ="Mark Complete";
                                                                                $itab_stat = "";
                                                                                $ilabelcolor = "tag tag-white";
                                                                                $ifontcolor ="color:black;";
                                                                           }
                                                                           ?>
                                                                           <label class="custom-switch" align="right">
                                                                                <input type="hidden" name="itemstatusid" value="<?php echo cy2_crypt($row_vignette_item1['item_id'],'e'); ?>">
                                                                                <input type="hidden" name="itemastatus" value="<?php echo $ivalue;?>">
                                                                                <input type="checkbox" name="custom-switch-checkbox" class="custom-switch-input" value="<?php echo $ivalue;?>" <?php echo $itab_stat;?> onChange='submit();'>
                                                                                <span class="custom-switch-indicator"></span>
                                                                                <span class="custom-switch-description"></span>
                                                                                <span align="right" class="<?php echo $ilabelcolor;?>"><b style="<?php echo $ifontcolor;?>"><?php echo $idvalue;?></b></span>
                                                                                <input type="hidden" name="entry_dtg"  id="entry_dtg" value="@ <?php date_default_timezone_set("Europe/London");$timestamp=time(); echo (date("F d, Y h:i:s A", $timestamp));?>; Updated by: <?php echo $colname_user_profile;?>">
                                                                           </label>
                                                                           <input type="hidden" name="update" value="updateactivitystatus">
                                                                      </form>
                                                                      <?php };?>
                                                                      <div class="time">Date: <b><?php $dtgstamp=new DateTime($row_vignette_item1['item_dtg']); echo date_format($dtgstamp, 'H:i');?></b> <?php $dtgstamp=new DateTime($row_vignette_item1['item_dtg']); echo date_format($dtgstamp, 'F d, Y');?></div>
                                                                      <h4><?php echo substr($row_vignette_item1['item_description'],0,200); ?>....</h4>
                                                                      <?php if ($itemtypes != 'Activity'){?>
                                                                      <small><span class="tag tag-dark"><a href="#<?php echo $hrefurl;?>" data-id="<?php echo cy2_crypt($row_vignette_item1['item_id'],'e'); ?>" role="button" data-toggle="modal" class="open" >Deploy</a></span></small>&nbsp;
                                                                      <?php };?>
                                                                      <?php if ($itemtypes != 'Activity'){?>
                                                                      <small><span class="tag tag-dark"><a href="#<?php echo $statusurl;?>" data-id="<?php echo cy2_crypt($row_vignette_item1['item_id'],'e'); ?>" role="button" data-toggle="modal" class="open" ><span class="spinner-grow text-success spinner-grow-sm"></span>&nbsp;Status&nbsp;&nbsp;</a></span></small>&nbsp;<br><br><br>
                                                                      <?php };?>
                                                                      <?php if ($itemtypes == 'Activity'){?>
                                                                      <small><span class="tag tag-dark"><a role="button" data-toggle="modal" class="open" ><span class="spinner-grow text-blue spinner-grow-sm"></span>&nbsp;<?php echo $astatus; ?>&nbsp;&nbsp;</a></span></small>&nbsp;<br><br><br>
                                                                      <?php };?>
                                                                 </div>
                                                                 <br>
                                                            <?php } while ($row_vignette_item1 = mysqli_fetch_assoc($vignette_item1)); ?>
                                                            <div class="bullet greem"></div>
                                                            <div class="time">Finish</div>
                                                       </li>
                                                  </ul>
                                             <?php } // Show if recordset not empty ?>
                                        </div>
                                   </div>

                                   <?php if ($totalRows_vignette_participants1 > 0) { // Show if recordset not empty ?>
                                   <div class="card bg-blue">
                                        <div class="card-header">
                                             <h3 class="card-title"><h6 class="m-0" style="color:white">Training Audience</h6><br></h3>
                                             <div class="card-options">
                                                  <a href="#" class="card-options-collapse" data-toggle="card-collapse" style="color:white"><i class="fe fe-chevron-up"></i></a>
                                                  <a href="#" class="card-options-remove" data-toggle="card-remove" style="color:white"><i class="fe fe-x"></i></a>
                                             </div>
                                        </div>
                                        <div class="card-body">
                                             <div class="mb-4">
                                                  <div class="media-body">
                                                       <?php do { ?>
                                                            <p>
                                                            <form method="POST" action="<?php echo $editFormAction; ?>" name="removeparticipants" id="removeparticipants">
                                                                 <td> <i class="<?php echo $row_vignette_participants1['icon'];?> fa-lg" style="color:white"> </i> <span class="tag tag-<?php echo $row_vignette_participants1['colour'];?>"><?php echo $row_vignette_participants1['pa_name'];?>  </span> </td>
                                                                 <button class="btn btn-icon btn-sm" title="Remove" data-toggle="tooltip" data-placement="top"><i class="icon-trash text-red"> </i></button>
                                                                 <input type="hidden" name="vignette_participants_id" value="<?php echo cy2_crypt($row_vignette_participants1['vignette_participants_id'],'e');?>">
                                                            </form>
                                                       <?php } while ($row_vignette_participants1 = mysqli_fetch_assoc($vignette_participants1)); ?>
                                                  </div>
                                             </div>
                                        </div>
                                   </div>
                                   <?php } // Show if recordset not empty ?>

                                   <?php if ($totalRows_vignetteterrain > 0) { // Show if recordset not empty ?>
                                   <div class="card bg-blue">
                                        <div class="card-header">
                                             <h3 class="card-title"><h6 class="m-0" style="color:white">Key Terrain</h6><br></h3>
                                             <div class="card-options">
                                                  <a href="#" class="card-options-collapse" data-toggle="card-collapse" style="color:white"><i class="fe fe-chevron-up"></i></a>
                                                  <a href="#" class="card-options-remove" data-toggle="card-remove" style="color:white"><i class="fe fe-x"></i></a>
                                             </div>
                                        </div>
                                        <div class="card-body">
                                             <div class="mb-4">
                                                  <div class="media-body">
                                                       <?php do { ?>
                                                            <p>
                                                            <form method="POST" action="<?php echo $editFormAction; ?>" name="removekt" id="removekt">
                                                                 <span class="tag tag-indigo"><?php echo $row_vignetteterrain['network'];?></span>
                                                                 <button class="btn btn-icon btn-sm" title="Remove" data-toggle="tooltip" data-placement="top">
                                                                      <i class="icon-trash text-red"> </i></button>
                                                                 </button>
                                                                 <input type="hidden" name="vignette_terrain_id" value="<?php echo cy2_crypt($row_vignetteterrain['vignette_terrain_id'],'e');?>">
                                                            </form>
                                                       <?php } while ($row_vignetteterrain = mysqli_fetch_assoc($vignetteterrain));?>
                                                  </div>
                                             </div>
                                        </div>
                                   </div>
                                   <?php } // Show if recordset not empty ?>

                                   <?php if ($totalRows_per_group > 0) { // Show if recordset not empty ?>
                                   <div class="card bg-primary">
                                        <div class="card-header">
                                             <h3 class="card-title"><h6 class="m-0" style="color:white">Persona Group</h6><br></h3>
                                             <div class="card-options">
                                                  <a href="#" class="card-options-collapse" data-toggle="card-collapse" style="color:white"><i class="fe fe-chevron-up"></i></a>
                                                  <a href="#" class="card-options-remove" data-toggle="card-remove" style="color:white"><i class="fe fe-x"></i></a>
                                             </div>
                                        </div>
                                        <div class="card-body">
                                             <div class="mb-4">
                                                  <div class="media-body">
                                                       <?php do { ?>
                                                            <p>
                                                            <form method="POST" action="<?php echo $editFormAction; ?>" name="removepersona" id="removepersona">
                                                                 <span class="tag tag-dark"><a href="excon-vignette-persona.php?group_id=<?php echo cy2_crypt($row_per_group['group_id'],'e');?>" style="color:white"><?php echo $row_per_group['group_name'];?></a></span>
                                                                 <button class="btn btn-icon btn-sm" title="Remove" data-toggle="tooltip" data-placement="top">
                                                                      <i class="icon-trash text-red"> </i></button>
                                                                 </button>
                                                                 <input type="hidden" name="vignette_persona_id" value="<?php echo cy2_crypt($row_per_group['vignette_persona_id'],'e');?>">
                                                            </form>
                                                       <?php } while ($row_per_group = mysqli_fetch_assoc($per_group));?>
                                                  </div>
                                             </div>
                                        </div>
                                   </div>
                                   <?php } // Show if recordset not empty ?>

                              </div>
                         </div>
                    </div>
               </div>
          </div>
     </div>















    <!-- Flow Chart Modal -->
    <div class="modal fade" id="flow-canvas" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
            <div class="modal-content">
                <div id="flow-modal-header" class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div id="flowchart-canvas" >
                    <div class="blockelem noselect block" id="0" style="left:500px; top: 100px; background-color:#217CE8;" ><input type="hidden" name="blockelemtype" class="blockelemtype" value="0"><input type="hidden" name="blockid" class="blockid" value="0">
                    <div class="blockyleft"><img src="assets/timeblue.svg"><p class="blockyname" style="color:white;">Itinerary Start</p></div></div>
                    <div class="list">
                        <ul>
                            <li> hello world </li>
                            <li> hello jupiter </li>
                            <li> hello mars </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello jupiter </li>
                            <li> hello mars </li>
                            <li> hello world </li>
                            <li> hello jupiter </li>
                            <li> hello mars </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello jupiter </li>
                            <li> hello mars </li>
                            <li> hello world </li>
                            <li> hello jupiter </li>
                            <li> hello mars </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello jupiter </li>
                            <li> hello mars </li>
                            <li> hello world </li>
                            <li> hello jupiter </li>
                            <li> hello mars </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello jupiter </li>
                            <li> hello mars </li>
                            <li> hello world </li>
                            <li> hello jupiter </li>
                            <li> hello mars </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello jupiter </li>
                            <li> hello mars </li><li> hello world </li>
                            <li> hello jupiter </li>
                            <li> hello mars </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello jupiter </li>
                            <li> hello mars </li><li> hello world </li>
                            <li> hello jupiter </li>
                            <li> hello mars </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello jupiter </li>
                            <li> hello mars </li>
                            <li> hello world </li>
                            <li> hello jupiter </li>
                            <li> hello mars </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello jupiter </li>
                            <li> hello mars </li>
                            <li> hello world </li>
                            <li> hello jupiter </li>
                            <li> hello mars </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello jupiter </li>
                            <li> hello mars </li>
                            <li> hello world </li>
                            <li> hello jupiter </li>
                            <li> hello mars </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello jupiter </li>
                            <li> hello mars </li><li> hello world </li>
                            <li> hello jupiter </li>
                            <li> hello mars </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello jupiter </li>
                            <li> hello mars </li>
                            <li> hello world </li>
                            <li> hello jupiter </li>
                            <li> hello mars </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello jupiter </li>
                            <li> hello mars </li>
                            <li> hello world </li>
                            <li> hello jupiter </li>
                            <li> hello mars </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello jupiter </li>
                            <li> hello mars </li>
                            <li> hello world </li>
                            <li> hello jupiter </li>
                            <li> hello mars </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello world </li>
                            <li> hello jupiter </li>
                            <li> hello mars </li>




                        </ul>
                    </div>
                </div>
                <div id="leftcard">
                    <div id="add-notes">
                        <div id="notes-button">
                            <button type="submit" id="add-note-button" >+ Add Note</button>
                        </div>
                    </div>
                    <div id="subnav">
                        <div id="Items" class="navactive side">Items</div>
                        <div id="Requests" class="navdisabled side">Requests</div>
                        <div id="Interactions" class="navdisabled side">Interactions</div>
                    </div>
                    <div id="blocklist">
                        <!-- HTML generated in main.js -->
                    </div>
                </div>
            </div>
        </div>
    </div>






















     <!-- Lesson Identified Modal -->
     <div class="modal fade" id="newlessonidentified" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
               <div class="modal-content">
                    <div class="modal-header">
                         <h5 class="modal-title" id="exampleModalLabel">Lesson Identified</h5>
                         <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <form method="POST" action="<?php echo $editFormAction; ?>" name="lessonidentified" id="lessonidentified">
                         <div class="modal-body">
                              <div class="row clearfix">
                                   <div class="col-md-12">
                                        <div class="form-group">
                                             <label class="form-label" style="color:black">Description</label>
                                             <textarea name="lesson_identified" cols="240" rows="5" class="form-control" placeholder="Lesson Identified..." required></textarea>
                                        </div>
                                   </div>
                              </div>
                         </div>
                         <div class="modal-footer">
                              <button type="submit" class="btn btn-primary">Submit</button>
                              <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                         </div>
                         <input type="hidden" name="exliname" id="exliname" value="<?php echo cy2_crypt($vignettename,'e'); ?>">
                         <input type="hidden" name="recorded_by"  id="recorded_by" value="<?php echo $colname_user_profile;?>">
                         <input type="hidden" name="dtg_recorded"  id="dtg_recorded" value="<?php date_default_timezone_set("Europe/London");$timestamp=time(); echo (date("F d, Y h:i:s A", $timestamp));?>">
                         <input type="hidden" name="insert" value="lessonidentified">
                    </form>
               </div>
          </div>
     </div>

     <!-- Assign Objective Modal -->
     <div class="modal fade" id="assignobj" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
               <div class="modal-content">
                    <div class="modal-header">
                         <h5 class="modal-title" id="exampleModalLabel">Assign Objectives</h5>
                         <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <form method="POST" action="<?php echo $editFormAction; ?>" name="assignobj" id="assignobj">
                         <div class="modal-body">
                              <div class="row clearfix">
                                   <div class="col-md-12">
                                        <div class="form-group multiselect_div">
                                             <label class="form-label" style="color:black">Vignette Objectives</label>
                                             <select id="multiselect2" name="multiselect6[]" class="multiselect multiselect-custom" multiple="multiple" required >
                                                  <option value="">-- Select Vignette Objective --</option>
                                                  <?php do { ?>
                                                       <option value="<?php echo cy2_crypt($row_vigobj['obj_id'],'e'); ?>"><?php echo $row_vigobj['obj_title']; ?></option>
                                                  <?php } while ($row_vigobj = mysqli_fetch_assoc($vigobj)); ?>
                                             </select>
                                        </div>
                                   </div>
                              </div>
                         </div>

                         <div class="modal-footer">
                              <button type="submit" class="btn btn-primary">Submit</button>
                              <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                         </div>
                         <input type="hidden" name="vigid" id="vigid" value="<?php echo cy2_crypt($vignetteid,'e'); ?>">
                         <input type="hidden" name="insert" value="assignobj">
                    </form>
               </div>
          </div>
     </div>

     <!-- Excon Log Modal -->
     <div class="modal fade" id="exconlog" tabindex="-1" data-backdrop="static">
          <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
               <div class="modal-content" style="background-color:#000000; color:gold">
                    <div class="modal-header">
                         <h5 class="modal-title" id="exampleModalLabel">To Do List</h5>
                         <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:yellow"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <br>
                    <div class="col-12">
                         <div class="card" style="background-color:#ffffff;color:black">
                              <div class="card-body">
                                   <form method="POST" action="<?php echo $editFormAction; ?>" name="addlogtask" id="addlogtask">
                                        <div class="modal-body">
                                             <div class="row clearfix">
                                                  <div class="col-md-12">
                                                       <div class="form-group">
                                                            <label class="form-label" style="color:black">Task</label>
                                                            <textarea name="log_detail" cols="240" rows="2" class="form-control" placeholder="Task Detail..." required></textarea>
                                                       </div>
                                                  </div>
                                             </div>
                                        </div>
                                        <div class="modal-footer">
                                             <button type="submit" class="btn btn-primary" <?php if ($eventno == null) { ?> disabled <?php } ?>>Submit</button>
                                        </div>
                                        <input type="hidden" name="evt_id" id="evt_id" value="<?php echo cy2_crypt($eventno,'e'); ?>">
                                        <input type="hidden" name="vgt_id" id="vgt_id" value="<?php echo cy2_crypt($vignetteid,'e'); ?>">
                                        <input type="hidden" name="recorded_by"  id="recorded_by" value="<?php echo $colname_user_profile;?>">
                                        <input type="hidden" name="dtg_recorded"  id="dtg_recorded" value="<?php date_default_timezone_set("Europe/London");$timestamp=time(); echo (date("F d, Y h:i:s A", $timestamp));?>">
                                        <input type="hidden" name="insert" value="addlogtask">
                                   </form>
                              </div>
                         </div>
                    </div>

                    <?php if ($totalRows_exconlog > 0) { // Show if recordset not empty ?>
                    <div class="col-12">
                         <div class="card" style="color:black">
                              <div class="card-body">
                                   <div class="table-responsive todo_list">
                                        <table class="table table-hover table-striped table-vcenter mb-0">
                                             <thead>
                                                  <tr style="background-color:#000000; color:white">
                                                       <th>Task</th>
                                                       <th class="w150 text-right">Date Created</th>
                                                       <th class="w80"><i class="icon-user"></i></th>
                                                       <th class="w40"></th>
                                                       <th class="w40"></th>
                                                  </tr>
                                             </thead>
                                             <tbody>
                                                  <?php do { ?>
                                                  <tr>
                                                       <td>
                                                            <label class="custom-control custom-checkbox">
                                                                 <input type="checkbox" class="custom-control-input" name="example-checkbox1" value="option1" <?php echo $row_exconlog['status'];?> disabled readonly>
                                                                 <span class="custom-control-label"><?php echo $row_exconlog['log_detail'];?></span>
                                                            </label>
                                                       </td>
                                                       <td class="text-right"><?php echo $row_exconlog['dtg_recorded'];?></td>
                                                       <td>
                                                            <span class="tag tag-dark ml-0 mr-0"><?php echo $row_exconlog['recorded_by'];?></span>
                                                       </td>

                                                       <form method="POST" action="<?php echo $editFormAction; ?>" name="changestatus" id="changestatus">
                                                            <td class="text-right">
                                                                 <select name="status" id="<?php echo$row_exconlog['exlog_id'];?>">
                                                                      <option value="">Select</option>
                                                                      <option value="checked">Completed</option>
                                                                      <option value="to do">Pending</option>
                                                                 </select>
                                                            </td>
                                                            <input type="hidden" name="exlog_id"  id="exlog_id" value="<?php echo cy2_crypt($row_exconlog['exlog_id'],'e');?>">
                                                            <input type="hidden" name="update" value="changestatus">
                                                       </form>
                                                       <script type="text/javascript">
                                                            jQuery(function() {
                                                                 jQuery('#<?php echo$row_exconlog['exlog_id'];?>').change(function() {
                                                                      this.form.submit();
                                                                 });
                                                            });
                                                       </script>
                                                       <td>
                                                            <form method="POST" action="<?php echo $editFormAction; ?>" name="removelog" id="removelog">
                                                                 <button class="btn btn-icon btn-sm" title="Remove" data-toggle="tooltip" data-placement="top"><i class="icon-trash text-red"></i></button></h3>
                                                                 <input type="hidden" name="exlog_id"  id="exlog_id" value="<?php echo cy2_crypt($row_exconlog['exlog_id'],'e');?>">
                                                                 <input type="hidden" name="delete" value="removelog">
                                                            </form>
                                                       </td>
                                                  </tr>
                                                  <?php } while ($row_exconlog = mysqli_fetch_assoc($exconlog)); ?>
                                             </tbody>
                                        </table>
                                   </div>
                              </div>
                         </div>
                    </div>
                    <?php };?>
               </div>
          </div>
     </div>


     <!-- Item Upload Modal -->
     <div class="modal fade" id="uploaditem" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
               <div class="modal-content">
                    <div class="modal-header">
                         <h5 class="modal-title" id="exampleModalLabel">Upload Item</h5>
                         <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <form method="POST" action="<?php echo $editFormAction; ?>" enctype="multipart/form-data" name="uploaditem" id="uploaditem">
                         <div class="modal-body">
                              <div class="row clearfix">
                                   <div class="col-md-12">
                                        <div class="form-group">
                                             <label class="form-label" style="color:black">Item Name</label>
                                             <input type="item_name" name="item_name" class="form-control" placeholder="Item Name" autocomplete="off" value="<?php echo isset($_SESSION['formdata']["item_name"]) ? htmlentities($_SESSION['formdata']["item_name"]) : ''; ?>" required >
                                        </div>
                                   </div>
                                   <div class="col-md-12">
                                        <div class="form-group multiselect_div">
                                             <label class="form-label" style="color:black">Item Category</label>
                                             <select id="multiselect5" name="item_category" class="multiselect multiselect-custom" multiple="multiple"  required >
                                                  <option value="Artefact"
                                                      <?php if (isset($_SESSION['formdata']["item_category"]) && $_SESSION['formdata']["item_category"] == 'Artefact') {
                                                          echo 'selected = "selected"';
                                                      } ?>
                                                  >Artefact</option>
                                                  <option value="Inject"
                                                      <?php if (isset($_SESSION['formdata']["item_category"]) && $_SESSION['formdata']["item_category"] == 'Inject') {
                                                          echo 'selected = "selected"';
                                                      } ?>
                                                  >Inject</option>
                                             </select>
                                        </div>
                                   </div>
                                   <div class="col-md-12">
                                        <div class="form-group multiselect_div">
                                             <label class="form-label" style="color:black">Type</label>
                                             <select id="multiselect5" name="multiselect5" class="multiselect multiselect-custom" multiple="multiple" required >
                                                  <?php do { ?>
                                                       <option value="<?php echo $row_itemtype['item_type']; ?>"
                                                           <?php if (isset($_SESSION['formdata']["multiselect5"]) && $_SESSION['formdata']["multiselect5"] == $row_itemtype['item_type']) {
                                                               echo 'selected = "selected"';
                                                           } ?>
                                                       ><?php echo $row_itemtype['item_type']; ?></option>
                                                  <?php } while ($row_itemtype = mysqli_fetch_assoc($itemtype)); ?>
                                             </select>
                                        </div>
                                   </div>
                                   <div class="col-md-12">
                                        <div class="form-group">
                                             <label class="form-label" style="color:black">Description</label>
                                             <textarea name="item_description" cols="240" rows="5" class="form-control" placeholder="Description..." required><?php echo isset($_SESSION['formdata']["item_description"]) ? htmlentities($_SESSION['formdata']["item_description"]) : ''; ?></textarea>
                                        </div>
                                   </div>
                                   <div class="col-md-12">
                                        <div class="form-group">
                                             <label class="form-label" style="color:black">DTG to send the item:</label>
                                             <div class="input-group date form_datetime col-md-5">
                                                  <input class="form-control" data-date="" data-date-format="yyyy-mm-dd hh:i" name="item_dtg" autocomplete="off" type="text" value="<?php echo isset($_SESSION['formdata']["item_dtg"]) ? htmlentities($_SESSION['formdata']["item_dtg"]) : ''; ?>" required>
                                                  <span class="input-group-addon"><span class="fa fa-calendar"></span></span>
                                             </div>
                                        </div>
                                   </div>
                                   <script type="text/javascript">
                                        $('.form_datetime').datetimepicker({
                                        weekStart: 1,
                                        todayBtn:  1,
                                        autoclose: 1,
                                        todayHighlight: 1,
                                        startView: 2,
                                        forceParse: 0,
                                        showMeridian: 1
                                        });
                                        $('.form_date').datetimepicker({
                                        weekStart: 1,
                                        todayBtn:  1,
                                        autoclose: 1,
                                        todayHighlight: 1,
                                        startView: 2,
                                        minView: 2,
                                        forceParse: 0
                                        });
                                        $('.form_time').datetimepicker({
                                        weekStart: 1,
                                        todayBtn:  1,
                                        autoclose: 1,
                                        todayHighlight: 1,
                                        startView: 1,
                                        minView: 0,
                                        maxView: 1,
                                        forceParse: 0
                                        });
                                   </script>
                                   <div class="col-md-12">
                                        <div class="form-group">
                                             <label class="form-label"></label>
                                             <input type="hidden" class="form-control" name="afilename" id="afilename" > <!-- causing issues  -->
                                             <label class="form-label">File type: <font color="red">pdf, pcap, csv, doc, docx, txt, ppt, pptx, xls, xlsx, jpg, jpeg, png</font></label>
                                             <label class="form-label">Maximum file size <font color="red">15MB</font></label>
                                             <input type="file" name="asfile" id="asfile" class="dropify" required>  <!-- causing issues    -->
                                        </div>
                                   </div>
                              </div>
                         </div>
                         <div class="modal-footer">
                              <button type="submit" class="btn btn-primary">Upload</button>
                              <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                         </div>
                         <input type="hidden" name="insert" value="uploaditem">
                    </form>
               </div>
          </div>
     </div>

     <!-- Activity create Modal -->
     <div class="modal fade" id="addactivity" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
               <div class="modal-content">
                    <div class="modal-header">
                         <h5 class="modal-title" id="exampleModalLabel">Add Activity</h5>
                         <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <form method="POST" action="<?php echo $editFormAction; ?>" name="addactivity" id="addactivity">
                         <div class="modal-body">
                              <div class="row clearfix">
                                   <div class="col-md-12">
                                        <div class="form-group">
                                             <label class="form-label" style="color:black">Activity Name</label>
                                             <input type="item_name" name="activity_name" class="form-control" placeholder="Activity Name" autocomplete="off" value="<?php echo isset($_SESSION['formdata']["activity_name"]) ? htmlentities($_SESSION['formdata']["activity_name"]) : ''; ?>" required />
                                        </div>
                                   </div>
                                   <div class="col-md-12">
                                        <div class="form-group">
                                             <label class="form-label" style="color:black">Actvity Date/Time:</label>
                                             <div class="input-group date form_datetime col-md-5">
                                                  <input class="form-control" data-date="" data-date-format="yyyy-mm-dd hh:i" name="activity_dtg" autocomplete="off" type="text" value="<?php echo isset($_SESSION['formdata']["activity_dtg"]) ? htmlentities($_SESSION['formdata']["activity_dtg"]) : ''; ?>" required>
                                                  <span class="input-group-addon"><span class="fa fa-calendar"></span></span>
                                             </div>
                                        </div>
                                   </div>
                                   <script type="text/javascript">
                                        $('.form_datetime').datetimepicker({
                                        weekStart: 1,
                                        todayBtn:  1,
                                        autoclose: 1,
                                        todayHighlight: 1,
                                        startView: 2,
                                        forceParse: 0,
                                        showMeridian: 1
                                        });
                                        $('.form_date').datetimepicker({
                                        weekStart: 1,
                                        todayBtn:  1,
                                        autoclose: 1,
                                        todayHighlight: 1,
                                        startView: 2,
                                        minView: 2,
                                        forceParse: 0
                                        });
                                        $('.form_time').datetimepicker({
                                        weekStart: 1,
                                        todayBtn:  1,
                                        autoclose: 1,
                                        todayHighlight: 1,
                                        startView: 1,
                                        minView: 0,
                                        maxView: 1,
                                        forceParse: 0
                                        });
                                   </script>
                                   <div class="col-md-12">
                                        <div class="form-group">
                                             <label class="form-label" style="color:black">Description</label>
                                             <textarea name="activity_description" cols="240" rows="5" class="form-control" placeholder="Description..." required><?php echo isset($_SESSION['formdata']["activity_description"]) ? htmlentities($_SESSION['formdata']["activity_description"]) : ''; ?></textarea>
                                        </div>
                                   </div>
                              </div>
                         </div>
                         <div class="modal-footer">
                              <input type="hidden" name="item_category" value="Activity">
                              <button type="submit" class="btn btn-primary">Add</button>
                              <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                         </div>
                         <input type="hidden" name="insert" value="addactivity">
                    </form>
               </div>
          </div>
     </div>

     <!-- Artefact Modal -->
     <div class="modal fade" id="assignartefact" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
          <div class="modal-dialog" role="document">
               <div class="modal-content">
                    <div class="modal-header">
                         <h5 class="modal-title" id="exampleModalLabel">Assign Artefact</h5>
                         <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>

                    <form method="POST" action="<?php echo $editFormAction; ?>" name="assignartefact" id="assignartefact">
                         <div class="modal-body">
                              <div class="row clearfix">
                                   <div class="col-md-12">
                                        <div class="form-group multiselect_div">
                                             <select id="multiselect5-filter" name="multiselect6[]" class="multiselect multiselect-custom" multiple="multiple" required>
                                                  <?php do { ?>
                                                       <option value="<?php echo cy2_crypt($row_vignetteartefact['item_id'],'e'); ?>"><?php echo $row_vignetteartefact['item_name']; ?></option>
                                                  <?php } while ($row_vignetteartefact = mysqli_fetch_assoc($vignetteartefact)); ?>
                                             </select>
                                        </div>
                                   </div>
                              </div>
                         </div>
                         <div class="modal-footer">
                              <button type="submit" class="btn btn-primary">Assign</button>
                              <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                         </div>
                         <input name="vignette_id2" type="hidden" class="form-control" id="vignette_id2" value="<?php echo cy2_crypt($row_vignette['vignette_id'],'e'); ?>">
                         <input type="hidden" name="insert" value="assignartefact">
                    </form>
               </div>
          </div>
     </div>
     <!-- Inject Modal -->
     <div class="modal fade" id="assigninject" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
          <div class="modal-dialog" role="document">
               <div class="modal-content">
                    <div class="modal-header">
                         <h5 class="modal-title" id="exampleModalLabel">Assign Injects</h5>
                         <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>

                    <form method="POST" action="<?php echo $editFormAction; ?>" name="assigninject" id="assigninject">
                         <div class="modal-body">
                              <div class="row clearfix">
                                   <div class="col-md-12">
                                        <div class="form-group multiselect_div">
                                             <select id="multiselect4-filter" name="multiselect6[]" class="multiselect multiselect-custom" multiple="multiple" required>
                                                  <?php do { ?>
                                                       <option value="<?php echo cy2_crypt($row_vignetteinj['item_id'],'e'); ?>"><?php echo $row_vignetteinj['item_name']; ?></option>
                                                  <?php } while ($row_vignetteinj = mysqli_fetch_assoc($vignetteinj)); ?>
                                             </select>
                                        </div>
                                   </div>
                              </div>
                         </div>
                         <div class="modal-footer">
                              <button type="submit" class="btn btn-primary">Assign</button>
                              <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                         </div>
                         <input name="vignette_id1" type="hidden" class="form-control" id="vignette_id1" value="<?php echo cy2_crypt($row_vignette['vignette_id'],'e'); ?>">
                         <input type="hidden" name="insert" value="assigninject">
                    </form>
               </div>
          </div>
     </div>
     <!-- Activity Modal -->
     <div class="modal fade" id="assignactivity" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
          <div class="modal-dialog" role="document">
               <div class="modal-content">
                    <div class="modal-header">
                         <h5 class="modal-title" id="exampleModalLabel">Assign Activity</h5>
                         <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>

                    <form method="POST" action="<?php echo $editFormAction; ?>" name="assignactivity" id="assignactivity">
                         <div class="modal-body">
                              <div class="row clearfix">
                                   <div class="col-md-12">
                                        <div class="form-group multiselect_div">
                                             <select id="multiselect1" name="multiselect6[]" class="multiselect multiselect-custom" multiple="multiple" required>
                                                  <?php do { ?>
                                                       <option value="<?php echo cy2_crypt($row_vignetteact['item_id'],'e'); ?>"><?php echo $row_vignetteact['item_name']; ?></option>
                                                  <?php } while ($row_vignetteact = mysqli_fetch_assoc($vignetteact)); ?>
                                             </select>
                                        </div>
                                   </div>
                              </div>
                         </div>
                         <div class="modal-footer">
                              <button type="submit" class="btn btn-primary">Assign</button>
                              <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                         </div>
                         <input name="vignette_id3" type="hidden" class="form-control" id="vignette_id3" value="<?php echo cy2_crypt($row_vignette['vignette_id'],'e'); ?>">
                         <input type="hidden" name="insert" value="assignactivity">
                    </form>
               </div>
          </div>
     </div>
     <!-- Training audience Modal -->
     <div class="modal fade" id="assignta" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
          <div class="modal-dialog" role="document">
               <div class="modal-content">
                    <div class="modal-header">
                         <h5 class="modal-title" id="exampleModalLabel">Assign Training Audience</h5>
                         <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>

                    <form method="POST" action="<?php echo $editFormAction; ?>" name="assignparticipants" id="assignparticipants">
                         <div class="modal-body">
                              <div class="row clearfix">
                                   <div class="col-md-12">
                                        <div class="form-group multiselect_div">
                                             <select id="multiselect2" name="multiselect6[]" class="multiselect multiselect-custom" multiple="multiple" required>
                                                  <?php do { ?>
                                                       <option value="<?php echo cy2_crypt($row_vignetteparticipants['pa_id'],'e'); ?>"><?php echo $row_vignetteparticipants['pa_name']; ?></option>
                                                  <?php } while ($row_vignetteparticipants = mysqli_fetch_assoc($vignetteparticipants)); ?>
                                             </select>
                                        </div>
                                   </div>
                              </div>
                         </div>
                         <div class="modal-footer">
                              <button type="submit" class="btn btn-primary">Assign</button>
                              <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                         </div>
                         <input type="hidden" name="vignette_id3" class="form-control" id="vignette_id3" value="<?php echo cy2_crypt($row_vignette['vignette_id'],'e'); ?>">
                         <input type="hidden" name="insert" value="assignparticipants">
                    </form>
               </div>
          </div>
     </div>

     <!-- Persona Modal -->
     <div class="modal fade" id="assignper" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
          <div class="modal-dialog" role="document">
               <div class="modal-content">
                    <div class="modal-header">
                         <h5 class="modal-title" id="exampleModalLabel">Assign Persona</h5>
                         <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>

                    <form method="POST" action="<?php echo $editFormAction; ?>" name="assignpersona" id="assignpersona">
                         <div class="modal-body">
                              <div class="row clearfix">
                                   <div class="col-md-12">
                                        <div class="form-group multiselect_div">
                                             <select id="multiselect2" name="multiselect6[]" class="multiselect multiselect-custom" multiple="multiple" required>
                                                  <?php do { ?>
                                                       <option value="<?php echo cy2_crypt($row_vignettepersonagroup['group_id'],'e'); ?>"><?php echo $row_vignettepersonagroup['group_name']; ?></option>
                                                  <?php } while ($row_vignettepersonagroup = mysqli_fetch_assoc($vignettepersonagroup)); ?>
                                             </select>
                                        </div>
                                   </div>
                              </div>
                         </div>
                         <div class="modal-footer">
                              <button type="submit" class="btn btn-primary">Assign</button>
                              <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                         </div>
                         <input type="hidden" name="exercise_name" class="form-control" id="exercise_name" value="<?php echo cy2_crypt($exercisedetail['slug'],'e'); ?>">
                         <input type="hidden" name="vignette_id5" class="form-control" id="vignette_id5" value="<?php echo cy2_crypt($row_vignette['vignette_id'],'e'); ?>">
                         <input type="hidden" name="insert" value="assignpersona">
                    </form>
               </div>
          </div>
     </div>

     <!-- KT Modal -->
     <div class="modal fade" id="assignkt" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
          <div class="modal-dialog" role="document">
               <div class="modal-content">
                    <div class="modal-header">
                         <h5 class="modal-title" id="exampleModalLabel">Assign Key Terrain</h5>
                         <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>

                    <form method="POST" action="<?php echo $editFormAction; ?>" name="assignkt" id="assignkt">
                         <div class="modal-body">
                              <div class="row clearfix">
                                   <div class="col-md-12">
                                        <div class="form-group multiselect_div">
                                             <select id="multiselect2" name="multiselect6[]" class="multiselect multiselect-custom" multiple="multiple" required>
                                                  <?php do { ?>
                                                       <option value="<?php echo cy2_crypt($row_keyterrain['network'],'e'); ?>"><?php echo $row_keyterrain['network']; ?></option>
                                                  <?php } while ($row_keyterrain = mysqli_fetch_assoc($keyterrain)); ?>
                                             </select>
                                        </div>
                                   </div>
                              </div>
                         </div>
                         <div class="modal-footer">
                              <button type="submit" class="btn btn-primary">Assign</button>
                              <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                         </div>
                         <input name="vignette_id4" type="hidden" class="form-control" id="vignette_id4" value="<?php echo cy2_crypt($row_vignette['vignette_id'],'e'); ?>">
                         <input type="hidden" name="insert" value="assignkt">
                    </form>
               </div>
          </div>
     </div>

     <!-- Vignette Artefact Items Modal -->
     <div class="modal fade" id="artefactstatus" tabindex="-1" data-backdrop="static">
          <div class="modal-dialog modal-dialog-top modal-xl" role="document">
               <div class="modal-content">
                    <div class="modal-header">
                         <h5 class="modal-title" id="exampleModalLabel">Artefact Status</h5>
                         <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:black"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <br>

                    <?php if ($totalRows_vignette_artefacts2 == 0 and $totalRows_vignette_artefacts3 == 0) { // Show if recordset not empty ?>
                    <div class="col-12">
                         <div class="card">
                              <div class="card-body">
                                   <div class="table-responsive todo_list">
                                        <table class="table ">
                                             <tbody>
                                                  <h6>Record Empty</h6>
                                             </tbody>
                                        </table>
                                   </div>
                              </div>
                         </div>
                    </div>
                    <?php };?>

                    <?php if ($totalRows_vignette_artefacts2 > 0) { // Show if recordset not empty ?>
                         <div class="card-body">
                              <div class="table-responsive todo_list">
                                   <table class="table ">
                                        <tbody>
                                             <tr class="thead-dark">
                                                  <th>Artefact Name</th>
                                                  <th class="w200">Remote Network</th>
                                                  <th class="w200">Node </th>
                                                  <th class="w200">Placement</th>
                                                  <th class="w100">Action</th>
                                             </tr>
                                             <?php do { ?>
                                                  <form method="POST" action="<?php echo $editFormAction; ?>" name="stopartefactdeploying" id="stopartefactdeploying">
                                                       <tr class="alert alert-danger">
                                                            <td><small><span><b><?php echo $row_vignette_artefacts2['item_name']; ?></b></small></span></td>
                                                            <td><small><span><?php echo $row_vignette_artefacts2['remote_network'];?></small></span></td>
                                                            <td><small><span><?php echo $row_vignette_artefacts2['remote_node'];?></small></span></td>
                                                            <td><small><span><?php echo $row_vignette_artefacts2['remote_dir']; ?></small></span></td>
                                                            <td> <button class="btn btn-icon btn-sm" title="Abort" data-toggle="tooltip" data-placement="top"><i class="fa fa-minus-circle text-red"></i></button></td>
                                                       </tr>
                                                       <input type="hidden" name="update" value="stopartefactdeploying">
                                                       <input type="hidden" name="status" value="Awaiting">
                                                       <input type="hidden" name="pl_art_id" value="<?php echo cy2_crypt($row_vignette_artefacts2['pl_art_id'],'e');?>">
                                                  </form>
                                             <?php } while ($row_vignette_artefacts2 = mysqli_fetch_assoc($vignette_artefacts2)); ?>
                                        </tbody>
                                   </table>
                              </div>
                         </div>
                    <?php };?>

                    <?php if ($totalRows_vignette_artefacts3 > 0) { // Show if recordset not empty ?>
                         <div class="card-body">
                              <div class="table-responsive todo_list">
                                   <table class="table">
                                        <tbody>
                                             <tr class="thead-dark">
                                                  <th>Artefact Name</th>
                                                  <th class="w200">Remote Network</th>
                                                  <th class="w200">Node </th>
                                                  <th class="w200">Placement</th>
                                                  <th class="w100">Status</th>
                                             </tr>
                                             <?php do { ?>
                                                  <?php
                                                  $artefactstatus = $row_vignette_artefacts3['status'];
                                                  if ($artefactstatus == "Awaiting"){
                                                       $labelcolor = "tag tag-red";
                                                  }
                                                  elseif ($artefactstatus == "Scheduled"){
                                                       $labelcolor = "tag tag-azure";
                                                  }
                                                  elseif ($artefactstatus == "Deploying"){
                                                       $labelcolor = "tag tag-lime";
                                                  }
                                                  elseif ($artefactstatus == "Deployed"){
                                                       $labelcolor = "tag tag-success";
                                                  }
                                                  ?>
                                                  <tr class="alert alert-secondary">
                                                       <td><small><span><b><?php echo $row_vignette_artefacts3['item_name']; ?></b></small></span></td>
                                                       <td><small><span><?php echo $row_vignette_artefacts3['remote_network'];?></small></span></td>
                                                       <td><small><span><?php echo $row_vignette_artefacts3['remote_node'];?></small></span></td>
                                                       <td><small><span><?php echo $row_vignette_artefacts3['remote_dir']; ?></small></span></td>
                                                       <td><small><span align="center" class="<?php echo $labelcolor;?>"><?php echo $row_vignette_artefacts3['status'];?></span></td>

                                                  </tr>
                                             <?php } while ($totalRows_vignette_artefacts3 = mysqli_fetch_assoc($vignette_artefacts3)); ?>
                                        </tbody>
                                   </table>
                              </div>
                         </div>
                    <?php };?>
               </div>
          </div>
     </div>

     <!-- Vignette Injects Items Modal -->
     <div class="modal fade" id="injectstatus" tabindex="-1" data-backdrop="static">
          <div class="modal-dialog modal-dialog-top modal-xl" role="document">
               <div class="modal-content">
                    <div class="modal-header">
                         <h5 class="modal-title" id="exampleModalLabel">Inject Status</h5>
                         <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:black"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <br>

                    <?php if ($totalRows_vignette_injects3 == 0 and $totalRows_vignette_injects2 == 0) { // Show if recordset not empty ?>
                    <div class="col-12">
                         <div class="card">
                              <div class="card-body">
                                   <div class="table-responsive todo_list">
                                        <table class="table ">
                                             <tbody>
                                                  <h6>Record Empty</h6>
                                             </tbody>
                                        </table>
                                   </div>
                              </div>
                         </div>
                    </div>
                    <?php };?>

                    <?php if ($totalRows_vignette_injects3 > 0) { // Show if recordset not empty ?>
                    <div class="card-body">
                         <div class="table-responsive">
                              <table class="table text-nowrap mb-0">
                                   <tbody>
                                        <tr class="thead-dark">
                                             <th>Inject Name</th>
                                             <th>Sender</th>
                                             <th>Recipient</th>
                                             <th class="w100">Subject</th>
                                             <th class="w300">Message</th>
                                             <th class="w100">Action</th>
                                        </tr>
                                        <?php do { ?>
                                             <form method="POST" action="<?php echo $editFormAction; ?>" name="stopdeployment" id="stopdeployment">
                                                  <tr class="alert alert-danger">
                                                       <td><small><span><b><?php echo $row_vignette_injects3['item_name']; ?></b></small></span></td>
                                                       <td><small><span> <?php echo $row_vignette_injects3['mail_sender'];?></small></span></td>
                                                       <td><small><span> <?php echo substr($row_vignette_injects3['mail_recipient'],0,50);?></small></span></td>
                                                       <td><small><span> <?php echo $row_vignette_injects3['mail_subject'];?></small></span></td>
                                                       <td><small><span> <?php echo substr($row_vignette_injects3['mail_message'],0,50); ?>...</span></td>
                                                       <input type="hidden" name="item_id" value="<?php echo cy2_crypt($row_vignette_injects3['item_id'],'e');?>">
                                                       <td> <button class="btn btn-icon btn-sm" title="Abort" data-toggle="tooltip" data-placement="top"><i class="fa fa-minus-circle text-red"></i></button></td>
                                                  </tr>
                                                  <input type="hidden" name="email_inj_id" value="<?php echo cy2_crypt($row_vignette_injects3['email_inj_id'],'e');?>">
                                                  <input type="hidden" name="delete" value="stopdeployment">
                                             </form>
                                        <?php } while ($row_vignette_injects3 = mysqli_fetch_assoc($vignette_injects3)); ?>
                                   </tbody>
                              </table>
                         </div>
                    </div>
                    <?php } // Show if recordset not empty ?>
                    <?php if ($totalRows_vignette_injects2 > 0) { // Show if recordset not empty ?>
                    <div class="card-body">
                         <div class="table-responsive">
                              <table class="table text-nowrap mb-0">
                                   <tbody>
                                        <tr class="thead-dark">
                                             <th>Inject Name</th>
                                             <th>Sender</th>
                                             <th>Recipient</th>
                                             <th class="w100">Subject</th>
                                             <th class="w300">Message</th>
                                             <th class="w100">Action</th>
                                        </tr>
                                        <?php do { ?>
                                        <?php
                                        $injectstatus = $row_vignette_injects2['mail_status'];
                                        if ($injectstatus == "Deploying"){
                                             $labelcolor = "tag tag-red";
                                        }
                                        elseif ($injectstatus == "Deployed"){
                                             $labelcolor = "tag tag-success";
                                        }
                                        else {
                                             $labelcolor = "tag tag-lime";
                                        }
                                        ?>
                                        <tr class="alert alert-secondary">
                                             <td><small><span><b><?php echo $row_vignette_injects2['item_name']; ?></b></small></span></td>
                                             <td><small><span> <?php echo $row_vignette_injects2['mail_sender'];?></small></span></td>
                                             <td><small><span> <?php echo substr($row_vignette_injects2['mail_recipient'],0,50);?></small></span></td>
                                             <td><small><span> <?php echo $row_vignette_injects2['mail_subject'];?></small></span></td>
                                             <td><small><span> <?php echo substr($row_vignette_injects2['mail_message'],0,50); ?>...</span></td>
                                             <td>
                                                  <span align="center" class="<?php echo $labelcolor;?>"><?php echo $row_vignette_injects2['mail_status'];?>
                                                  </span>
                                             </td>
                                        </tr>
                                        <?php } while ($row_vignette_injects2 = mysqli_fetch_assoc($vignette_injects2)); ?>
                                   </tbody>
                              </table>
                         </div>
                    </div>
                    <?php } // Show if recordset not empty ?>
               </div>
          </div>
     </div>

     <!-- Deploy Artefacts Modal -->
     <div class="modal fade" id="deployartefact" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered" role="document">
               <div class="modal-content">
                    <div class="modal-header">
                         <h5 class="modal-title" id="exampleModalLabel">Deploy Artefacts</h5>
                         <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>

                    <form method="POST" action="<?php echo $editFormAction; ?>" name="deployartefact" id="deployartefact">
                         <div class="modal-body">
                              <div class="row clearfix">
                                   <input type="hidden" name="item_id" id="item_id" value=""/>
                                   <div class="col-md-12">
                                        <div class="form-group multiselect_div">
                                             <label class="form-label" style="color:black">Target Network</label>
                                             <select id="multiselect5" name="remote_network" class="multiselect multiselect-custom" multiple="multiple" required >
                                                  <?php do { ?>
                                                       <option value="<?php echo $row_target_kt2['network']; ?>"><?php echo $row_target_kt2['network']; ?></option>
                                                  <?php } while ($row_target_kt2 = mysqli_fetch_assoc($target_kt2)); ?>
                                             </select>
                                        </div>
                                   </div>
                                   <div class="col-md-12">
                                        <div class="form-group multiselect_div">
                                             <label class="form-label" style="color:black">Deploy to IP Address</label>
                                             <select id="multiselect5" name="remote_node" class="multiselect multiselect-custom" multiple="multiple" required >
                                                  <?php do { ?>
                                                       <option value="<?php echo $row_target_kt3['ip']; ?>"><?php echo $row_target_kt3['ip']; ?> (<?php echo $row_target_kt3['network']; ?>)</option>
                                                  <?php } while ($row_target_kt3 = mysqli_fetch_assoc($target_kt3)); ?>
                                             </select>
                                        </div>
                                   </div>
                                   <div class="col-md-12">
                                        <div class="form-group">
                                             <label class="form-label" style="color:black">Deploy Directory</label>
                                             <input type="text" name="remote_dir" class="form-control" placeholder="C:\Users\Desktop" autocomplete="off" required />
                                        </div>
                                   </div>
                              </div>
                         </div>
                         <input type="hidden" name="entry_dtg"  id="entry_dtg" value="@ <?php date_default_timezone_set("Europe/London");$timestamp=time(); echo (date("F d, Y h:i:s A", $timestamp));?>; Updated by: <?php echo $colname_user_profile;?>">
                         <input type="hidden" name="description"  id="description" value="Deploying">
                         <div class="modal-footer">
                              <button type="submit" class="btn btn-primary">Deploy</button>
                              <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                         </div>
                         <input type="hidden" name="status" value="Deploying">
                         <input type="hidden" name="typeid" value="<?php echo cy2_crypt($vignetteid,'e');?>">
                         <input type="hidden" name="insert" value="deployartefact">
                    </form>
               </div>
          </div>
     </div>

     <div class="modal fade" id="injectemail" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
               <div class="modal-content">
                    <div class="modal-header">
                         <h5 class="modal-title" id="exampleModalLabel">Email Inject</h5>
                         <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <form method="POST" action="<?php echo $editFormAction; ?>" name="injectemail" id="injectemail">
                         <div class="modal-body">
                              <div class="row clearfix">
                                   <input type="hidden" name="item_id" id="item_id" value=""/>
                                   <div class="col-md-12">
                                        <div class="form-group">
                                             <label class="form-label" style="color:black">Sender</label>
                                             <input type="email" pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,4}$" name="mail_sender" class="form-control" placeholder="joeblog@mod.uk" autocomplete="off" required />
                                        </div>
                                   </div>
                                   <div class="col-md-12">
                                        <div class="form-group">
                                             <label class="form-label" style="color:black">Recipient</label>
                                             <input type="email" pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,4}$" name="mail_recipient" class="form-control" placeholder="james@gmail.com;tom@hotmail.com" autocomplete="off" required />
                                        </div>
                                   </div>
                                   <div class="col-md-12">
                                        <div class="form-group">
                                             <label class="form-label" style="color:black">Subject:</label>
                                             <input type="text" name="mail_subject" class="form-control" placeholder="REF:Weekly Report" autocomplete="off" required />
                                        </div>
                                   </div>
                                   <div class="col-md-12">
                                        <div class="form-group">
                                             <label class="form-label" style="color:black">Message</label>
                                             <textarea name="mail_message" cols="240" rows="10" class="form-control" placeholder="Mail message..." required></textarea>
                                        </div>
                                   </div>
                              </div>
                         </div>
                         <input type="hidden" name="entry_dtg"  id="entry_dtg" value="@ <?php date_default_timezone_set("Europe/London");$timestamp=time(); echo (date("F d, Y h:i:s A", $timestamp));?>; Updated by: <?php echo $colname_user_profile;?>">
                         <input type="hidden" name="description"  id="description" value="Deploying">
                         <div class="modal-footer">
                              <button type="submit" class="btn btn-primary">Send</button>
                              <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                         </div>
                         <input type="hidden" name="mail_status" value="Deploying">
                         <input type="hidden" name="typeid" value="<?php echo cy2_crypt($vignetteid,'e');?>">
                         <input type="hidden" name="insert" value="injectemail">
                    </form>
               </div>
          </div>
     </div>

     <div class="modal fade" id="email" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
               <div class="modal-content">
                    <div class="modal-header">
                         <h5 class="modal-title" id="exampleModalLabel">Email Inject</h5>
                         <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <form method="POST" action="<?php echo $editFormAction; ?>" name="email" id="email">
                         <div class="modal-body">
                              <div class="row clearfix">
                                   <div class="col-md-12">
                                        <div class="form-group">
                                             <label class="form-label" style="color:black">Sender</label>
                                             <input type="email" name="sender" class="form-control" placeholder="joeblog@mod.uk" autocomplete="off" required />
                                        </div>
                                   </div>
                                   <div class="col-md-12">
                                        <div class="form-group">
                                             <label class="form-label" style="color:black">Recipient</label>
                                             <input type="text" name="recipient" class="form-control" placeholder="james@gmail.com;tom@hotmail.com" autocomplete="off" required />
                                        </div>
                                   </div>
                                   <div class="col-md-12">
                                        <div class="form-group">
                                             <label class="form-label" style="color:black">Subject:</label>
                                             <input type="text" name="subject" class="form-control" placeholder="REF:Weekly Report" autocomplete="off" required />
                                        </div>
                                   </div>
                                   <div class="col-md-12">
                                        <div class="form-group">
                                             <label class="form-label" style="color:black">Message</label>
                                             <textarea name="message" cols="240" rows="10" class="form-control" placeholder="Mail message..." required></textarea>
                                        </div>
                                   </div>
                              </div>
                         </div>
                         <div class="modal-footer">
                              <button type="submit" class="btn btn-primary">Submit</button>
                              <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                         </div>
                         <input type="hidden" name="vignette_id" value="<?php echo cy2_crypt($vignetteid,'e');?>">
                         <input type="hidden" name="insert" value="email">
                    </form>
               </div>
          </div>
     </div>

     <div class="modal fade" id="injectnews" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
               <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                    <div class="modal-content">
                         <div class="modal-header">
                              <h5 class="modal-title" id="exampleModalLabel">News Inject</h5>
                              <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                         </div>
                         <form method="POST" action="<?php echo $editFormAction; ?>" name="exmanews" id="exmanews">
                              <div class="modal-body">
                                   <div class="row clearfix">
                                        <div class="col-md-6">
                                             <div class="form-group"><!-- form-group multiselect_div-->
                                                  <label class="form-label" style="color:black">Deploy News To</label>
                                                  <select class="form-control" id="site_name" name="site_name" required>
                                                       <option value="" >-- Select News Site --</option>
                                                       <option value="EXMANEWS">EXMANEWS</option>
                                                  </select>
                                             </div>
                                        </div>
                                        <div class="col-md-6">
                                             <div class="form-group">
                                                  <label class="form-label" style="color:black">News Type</label>
                                                  <select class="form-control" id="table" name="table" required>
                                                       <option value="" >-- Select News Type --</option>
                                                       <option value="worldnews">World News</option>
                                                       <option value="politics">Politics</option>
                                                       <option value="sports">Sports</option>
                                                       <option value="technology">Technology</option>
                                                       <option value="blog">Blog</option>
                                                  </select>
                                             </div>
                                        </div>
                                        <div class="col-md-12">
                                             <div class="form-group">
                                                  <label class="form-label" style="color:black">News Title</label>
                                                  <input type="text" name="headline" class="form-control" placeholder="Headlines" autocomplete="off" required />
                                             </div>
                                        </div>
                                        <div class="col-md-6">
                                             <div class="form-group">
                                                  <label class="form-label" style="color:black">Date</label>
                                                  <input type="text" name="date" class="form-control" placeholder="Date Published" autocomplete="off" required />
                                             </div>
                                        </div>
                                        <div class="col-md-6">
                                             <div class="form-group">
                                                  <label class="form-label" style="color:black">Journalist:</label>
                                                  <input type="text" name="author" class="form-control" placeholder="News Journalist" autocomplete="off" required />
                                             </div>
                                        </div>
                                        <div class="col-md-12">
                                             <div class="form-group">
                                                  <label class="form-label">Select News Image</label>
                                                  <select class="form-control" id="img" name="img" required>
                                                       <option value="">-- Select Image --</option>
                                                       <?php do { ?>
                                                            <option value="<?php echo $row_exmimg['img_id'];?>"><?php echo $row_exmimg['imgname'];?></option>
                                                       <?php } while ($row_exmimg = mysqli_fetch_assoc($exmimg)); ?>
                                                  </select>
                                             </div>
                                        </div>
                                        <div class="col-md-12">
                                             <div class="form-group">
                                                  <label class="form-label" style="color:black">News</label>
                                                  <textarea name="par1" cols="240" rows="10" class="form-control" placeholder="News..." required></textarea>
                                             </div>
                                        </div>
                                   </div>
                              </div>
                              <div class="modal-footer">
                                   <button type="submit" class="btn btn-primary">Publish</button>
                                   <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                              </div>
                              <input type="hidden" name="insert" value="exmanews">
                         </form>
                    </div>
               </div>
          </div>

     <div class="modal fade" id="uploadproduct" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered" role="document">
               <div class="modal-content">
                    <div class="modal-header">
                         <h5 class="modal-title" id="exampleModalLabel">Upload Player Product</h5>
                         <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <form method="POST" action="<?php echo $editFormAction; ?>" enctype="multipart/form-data" name="uploadproduct" id="uploadproduct">
                         <div class="modal-body">
                              <div class="row clearfix">
                                   <div class="col-md-12">
                                        <div class="form-group">
                                             <label class="form-label" style="color:black">Product Name</label>
                                             <input type="name" name="name" class="form-control" placeholder="Product Name" autocomplete="off" value="<?php echo isset($_SESSION['formdata']["name"]) ? htmlentities($_SESSION['formdata']["name"]) : ''; ?>" required />
                                        </div>
                                   </div>
                                   <div class="col-md-12">
                                        <div class="form-group multiselect_div">
                                             <label class="form-label" style="color:black">Training Audience</label>
                                             <select id="multiselect5" name="training_audience" class="multiselect multiselect-custom" multiple="multiple" required >
                                                  <?php do { ?>
                                                       <option value="<?php echo $row_vignette_participants2['pa_name']; ?>"
                                                           <?php if (isset($_SESSION['formdata']["training_audience"]) && $_SESSION['formdata']["training_audience"] == $row_vignette_participants2['pa_name']) {
                                                               echo 'selected = "selected"';
                                                           } ?>
                                                       ><?php echo $row_vignette_participants2['pa_name']; ?></option>
                                                  <?php } while ($row_vignette_participants2 = mysqli_fetch_assoc($vignette_participants2)); ?>
                                             </select>
                                        </div>
                                   </div>
                                   <div class="col-md-12">
                                        <div class="form-group">
                                             <label class="form-label" style="color:black">Description</label>
                                             <textarea name="description" cols="240" rows="5" class="form-control" placeholder="Description..." required><?php echo isset($_SESSION['formdata']["description"]) ? htmlentities($_SESSION['formdata']["description"]) : ''; ?></textarea>
                                        </div>
                                   </div>
                                   <div class="col-md-12">
                                        <div class="form-group">
                                             <label class="form-label"></label>
                                             <input type="hidden" class="form-control" name="filename" id="filename" >
                                             <label class="form-label">File type: <font color="red">pdf, pcap, csv, doc, docx, txt, ppt, pptx, xls, xlsx, jpg, jpeg, png</font></label>
                                             <label class="form-label">Maximum file size <font color="red">15MB</font></label>
                                             <input type="file" name="sfile" id="sfile" class="dropify" required>
                                        </div>
                                   </div>
                              </div>
                         </div>
                         <input type="hidden" name="entry_dtg"  id="entry_dtg" value="@ <?php date_default_timezone_set("Europe/London");$timestamp=time(); echo (date("F d, Y h:i:s A", $timestamp));?>; Updated by: <?php echo $colname_user_profile;?>">
                         <input type="hidden" name="status"  id="status" value="Player Product Uploaded">
                         <div class="modal-footer">
                              <button type="submit" class="btn btn-primary">Upload</button>
                              <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                         </div>
                         <input type="hidden" name="date_submitted" id="date_submitted" value="<?php date_default_timezone_set("Europe/London");$timestamp=time(); echo (date("F d, Y h:i:s A", $timestamp));?>">
                         <input type="hidden" name="vignette_id" value="<?php echo cy2_crypt($vignetteid,'e');?>">
                         <input type="hidden" name="insert" value="uploadproduct">
                    </form>
               </div>
          </div>
     </div>

     <div class="modal fade" id="deployvulnerability" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered" role="document">
               <div class="modal-content">
                    <div class="modal-header">
                         <h5 class="modal-title" id="exampleModalLabel">Vulnify Key Terrain</h5>
                         <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <form method="POST" action="<?php echo $editFormAction; ?>" name="vulnify" id="vulnify">
                         <div class="modal-body">
                              <div class="row clearfix">
                                   <div class="col-md-12">
                                        <div class="form-group">
                                             <label class="form-label" style="color:black">Vulnerable Technique</label>
                                             <select id="multiselect5" name="ap_id" class="multiselect multiselect-custom" multiple="multiple" required >
                                                  <?php do { ?>
                                                  <option value="<?php echo $row_vulnerability['ap_id']; ?>"><?php echo $row_vulnerability['ap_name']; ?></option>
                                                  <?php } while ($row_vulnerability = mysqli_fetch_assoc($vulnerability)); ?>
                                             </select>
                                        </div>
                                   </div>
                                   <div class="col-md-12">
                                        <div class="form-group multiselect_div">
                                             <label class="form-label" style="color:black">Target Network</label>
                                             <select id="multiselect5" name="network" class="multiselect multiselect-custom" multiple="multiple" required >
                                                  <?php do { ?>
                                                  <option value="<?php echo $row_target_kt['network']; ?>"><?php echo $row_target_kt['network']; ?></option>
                                                  <?php } while ($row_target_kt = mysqli_fetch_assoc($target_kt)); ?>
                                             </select>
                                        </div>
                                   </div>
                                   <div class="col-md-12">
                                        <div class="form-group multiselect_div">
                                             <label class="form-label" style="color:black">Node</label>
                                             <select id="multiselect5" name="target" class="multiselect multiselect-custom" multiple="multiple" required >
                                             <?php do { ?>
                                             <option value="<?php echo $row_target_kt1['mip']; ?>"><?php echo $row_target_kt1['ip']; ?> (<?php echo $row_target_kt1['network']; ?>)</option>
                                             <?php } while ($row_target_kt1 = mysqli_fetch_assoc($target_kt1)); ?>
                                             </select>
                                        </div>
                                   </div>
                              </div>
                         </div>
                         <input type="hidden" name="entry_dtg"  id="entry_dtg" value="@ <?php date_default_timezone_set("Europe/London");$timestamp=time(); echo (date("F d, Y h:i:s A", $timestamp));?>; Updated by: <?php echo $colname_user_profile;?>">
                         <input type="hidden" name="description"  id="description" value="Deploying">
                         <div class="modal-footer">
                              <button type="submit" class="btn btn-primary">Submit</button>
                              <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                         </div>
                         <input type="hidden" name="vignette_id" value="<?php echo cy2_crypt($vignetteid,'e');?>">
                         <input type="hidden" name="status" value="1">
                         <input type="hidden" name="insert" value="vulnify">
                    </form>
               </div>
          </div>
     </div>
     <!-- video Modal -->
     <div class="modal fade" id="video" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
               <div class="modal-content">
                    <div class="modal-body mb-0 p-0">
                         <div class="form-group">
                              <video controls
                              src="videos/<?php echo $row_vignettevideo['videofile'];?>"
                              width="798">

                              Sorry, your browser doesn't support embedded videos.
                              </video>
                         </div>
                    </div>
                    <div class="modal-footer justify-content-center flex-column flex-md-row">
                         <button type="button" class="btn btn-outline-primary btn-rounded btn-md ml-4" data-dismiss="modal">Close</button>
                    </div>
               </div>
          </div>
     </div>

</div>

<!-- -->
<script src="../assets/bundles/lib.vendor.bundle.js"></script>
<script src="../assets/js/core.js"></script>
<!-- Modal -->
<script src="../assets/plugins/bootstrap-colorpicker/js/bootstrap-colorpicker.js"></script>
<script src="../assets/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js"></script>
<script src="../assets/plugins/bootstrap-multiselect/bootstrap-multiselect.js"></script>
<script src="../assets/plugins/multi-select/js/jquery.multi-select.js"></script>
<script src="../assets/plugins/jquery.maskedinput/jquery.maskedinput.min.js"></script>
<script src="../assets/plugins/jquery-inputmask/jquery.inputmask.bundle.js"></script>
<script src="assets/js/form/form-advanced.js"></script>
<!-- Loading file -->
<script src="../assets/plugins/dropify/js/dropify.min.js"></script>
<script src="../assets/plugins/autopopulate/filename.js"></script>
<script src="../assets/plugins/autopopulate/filename1.js"></script>
<script type="text/javascript">
    function unlinkrecord() {
        var $promptResponse = prompt("Please type UNLINK to confirm that you want to unlink all the objects from the Vignette:");

        return $promptResponse === 'UNLINK';
    }
</script>
<script>
$(document).on("click", ".open", function () {
     var itemid = $(this).data('id');
     $(".modal-body #item_id").val( itemid );
});
</script>
</body>
</html>
<?php
mysqli_close($exmato);
mysqli_close($exercise);
mysqli_close($exmanews);
unset($_SESSION['formdata']);
?>
