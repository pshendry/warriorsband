<?php

/*
 *  event-exec.php
 *
 *  Validates and executes event creation/update requests
 *
 */

session_start();
require_once($_SERVER['DOCUMENT_ROOT'].'/auth/auth-functions.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/config/database.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/config/config.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/config/display.php');
set_include_path(get_include_path().'/Sites/warriorsband.com/pear'.PATH_SEPARATOR);
require_once("Mail.php");

//Ensure that the user is allowed to edit events
if (!auth_edit_events()) error_and_exit();

$redirect_url = "$domain?page=event";

//TRUE if we are creating a new event, FALSE if we are updating an existing one
$new_event = TRUE;

//If an event ID is provided, sanitize it and fetch the existing event info
if (isset($_POST['event_id'])) {
  $event_id = intval($_POST['event_id']);
  $new_event = FALSE;
  $redirect_url .= "&event_id=$event_id";

  //Make sure the event exists
  $num_events = $mysqli->query(
    "SELECT COUNT(*) FROM `events` WHERE `event_id`='$event_id'")->fetch_row();
  handle_sql_error($mysqli);
  if ($num_events[0] == 0) {
    error_and_exit("No event with that event ID.");
  }
}

//Get the event's current status if it exists already; otherwise default to 2
if (!$new_event) {
  $row = $mysqli->query(
    "SELECT `status` FROM `events` WHERE `event_id`='$event_id'")->fetch_row();
  $status = intval($row[0]);
} else {
  $status = 2;
}
$send_notification_emails = FALSE;

//If the mark_upcoming field is provided and the event is not upcoming, set it to be.
//Set the flag to send email notifications later also.
if (isset($_POST['mark_upcoming'])) {
  //If the event exists already, get its current status and make sure it has not
  //already been marked as upcoming
  if ($status == 1) {
    error_and_exit("Event already marked as upcoming.");
  }
  $status = 1;
  if (isset($_POST['send_email']) && user_type_greater_eq(3)) {
    $send_notification_emails = TRUE;
  }
}

//Validate title
$title = format_text(sanitize($_POST['title']));
if (empty($title) || strlen($title) == 0 || strlen($title) > 255) {
    header("Location: $redirect_url&msg=badtitle");
    exit();
}

//Construct and validate date
if (isset($_POST['no_date'])) {
  $date = "NULL";
} else {
  $date_day = intval($_POST['date_day']);
  $date_month = intval($_POST['date_month']);
  $date_year = intval($_POST['date_year']);
  $date = "'$date_year-$date_month-$date_day'";
  if (!checkdate($date_month, $date_day, $date_year)) {
    header("Location: $redirect_url&msg=baddate");
    exit();
  }
  if (mktime(0,0,0,$date_month,$date_day,$date_year) < strtotime(date("Y-m-d"))) {
    header("Location: $redirect_url&msg=pastdate");
    exit();
  }
}

//Construct and validate start and end time
if (isset($_POST['no_start_time'])) {
  $start_time = "NULL";
} else {
  $start_time_hour = intval($_POST['start_time_hour']);
  $start_time_minute = intval($_POST['start_time_minute']);
  $start_time_ampm = sanitize($_POST['start_time_ampm']);
  if (($start_time_hour < 1) || ($start_time_hour > 12) ||
      ($start_time_minute < 0) || ($start_time_minute > 59) ||
      (($start_time_ampm != "AM") && ($start_time_ampm != "PM"))) {
    header("Location: $redirect_url&msg=badtime");
    exit();
  }
  $start_time = "'" . date("H:i", strtotime("$start_time_hour:".str_pad($start_time_minute,2,"0",STR_PAD_LEFT).$start_time_ampm)) . "'";
}
if (isset($_POST['no_end_time'])) {
  $end_time = "NULL";
} else {
  $end_time_hour = intval($_POST['end_time_hour']);
  $end_time_minute = intval($_POST['end_time_minute']);
  $end_time_ampm = sanitize($_POST['end_time_ampm']);
  if (($end_time_hour < 1) || ($end_time_hour > 12) ||
      ($end_time_minute < 0) || ($end_time_minute > 59) ||
      (($end_time_ampm != "AM") && ($end_time_ampm != "PM"))) {
    header("Location: $redirect_url&msg=badtime");
    exit();
  }
  $end_time = "'" . date("H:i", strtotime("$end_time_hour:".str_pad($end_time_minute,2,"0",STR_PAD_LEFT).$end_time_ampm)) . "'";
}

//Validate location
$location = format_text(sanitize($_POST['location']));
if (strlen($location) > 255) {
    header("Location: $redirect_url&msg=badlocation");
    exit();
}

//Validate details
$details = sanitize($_POST['details']);
if (strlen($location) > 10000) {
  error_and_exit("Details must be less than 10000 characters.");
}

//If we need to send out notification emails, do so
if ($send_notification_emails) {
  //Get list of recipients (all names/emails of members who are currently marked as on campus)
  $result = $mysqli->query("SELECT `first_name`,`last_name`,`email`,`on_campus` FROM `users`");
  $recipients = array();
  while ($user_row = $result->fetch_assoc()) {
    if ($user_row['on_campus'] == 1) {
      $recipients[] = $user_row['first_name'] . " " . $user_row['last_name'] . " <" . $user_row['email'] . ">";
    }
  }
  $result->free();

  //Set up the e-mail details
  $from = "Warriors Band <$email_username>";
  $subject = event_notification_email_subject($title);
  $body = event_notification_email_message();
  $headers = array ('From' => $from, 
    'Subject' => $subject);
  $smtp = Mail::factory('smtp',
    array ('host' => $email_host,
    'port' => $email_port,
    'auth' => true,
    'username' => $email_username,
    'password' => $email_password));

  $mail = $smtp->send($recipients, $headers, $body);

  if (PEAR::isError($mail)) {
    header("Location: $redirect_url&msg=notificationemailfail");
    exit();
  }
}

//If this is a new event, do an insertion and update the reminder counter, otherwise do an update
if ($new_event) {
  $mysqli->query(
    "INSERT INTO `events` " .
    "(`status`,`creator_id`,`title`,`date`,`start_time`,`end_time`,`location`,`details`)" . 
    "VALUES ('$status','" . $_SESSION['user_id'] . "','$title',$date,$start_time,$end_time,'$location','$details')");
  handle_sql_error($mysqli);
} else {
  $mysqli->query(
    "UPDATE `events` SET `status`='$status',`creator_id`='" . $_SESSION['user_id'] . 
    "',`title`='$title',`date`=$date,`start_time`=$start_time,`end_time`=$end_time,`location`='$location'," . 
    "`details`='$details' WHERE `event_id`='$event_id'");
  handle_sql_error($mysqli);
}

//Redirect
if ($new_event) {
  header("Location: $domain?page=events&msg=eventcreatesuccess");
} else {
  header("Location: $redirect_url&msg=eventupdatesuccess");
}
exit();
?>
