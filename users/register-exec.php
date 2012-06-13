<?php

/*
 *  register-exec.php
 *
 *  Allows an authenticated user with high enough user_type to create new users. 
 *  Creates the new user and sends them an email with their password.
 */

session_start();
require($_SERVER['DOCUMENT_ROOT'].'/config/config.php');
require($_SERVER['DOCUMENT_ROOT'].'/config/display.php');
require($_SERVER['DOCUMENT_ROOT'].'/auth/auth.php');
require($_SERVER['DOCUMENT_ROOT'].'/auth/auth-functions.php');

//pre-define success
$success = FALSE;

//Check if user submitted the desired password and username
if (logged_in() && user_type_greater_eq(2) &&
  (isset($_POST['email'])) && (isset($_POST['first_name'])) &&
  (isset($_POST['last_name'])) && (isset($_POST['comment']))) {

  //Sanitize user inputs
  $email = sanitize($_POST['email']);
  $first_name = sanitize($_POST['first_name']);
  $last_name = sanitize($_POST['last_name']);
  $comment = sanitize($_POST['comment']);

  //Validate e-mail address
  if (!valid_email($email)) {
    header("Location: ".$domain."/users/register.php?msg=bademail");
    exit();
  }

  //Ensure the email address is not already taken
  if ($fetch = mysql_fetch_array( mysql_query("SELECT `email` FROM `users` WHERE `email`='$email'"))) {
    header("Location: ".$domain."/users/register.php?msg=duplicateemail");
    exit();
  }

  //Check if the names are too long
  if ((strlen($first_name) > 255) || (strlen($last_name) > 255)) {
    header("Location: ".$domain."/users/register.php?msg=nametoolong");
    exit();
  }
  //If names are not letters and dashes only, exit
  if ((!ctype_alpha(str_replace('-','',$first_name))) ||
      (!ctype_alpha(str_replace('-','',$last_name)))) {
    header("Location: ".$domain."/users/register.php?msg=nonalphaname");
    exit();
  }

  //Generate a random 8-char string to be the temporary password of the new account
  $charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
  $arr = str_split($charset);
  shuffle($arr);
  $arr = array_slice($arr, 0, 8);
  $temp_password = implode('', $arr);
  $hashedpassword= hash_password($temp_password);

  //Insert username and the hashed password to MySQL database
  mysql_query("INSERT INTO `users` (`last_name`, `first_name`, `email`, `password`) VALUES ('$last_name', '$first_name', '$email', '$hashedpassword')")
    or die(mysql_error());

  //Send an email to the newly registered account
  $subject = registration_email_subject();
  $message = registration_email_message($temp_password, $_SESSION['first_name'], $comment);
  $from = registration_email_from();
  $headers = "From: " . $from;
  mail($email, $subject, $message, $headers);

  //redirect back to registration page indicating success
  header("Location: ".$domain."/users/register.php?msg=registrationsuccess");
  exit();
}
?>