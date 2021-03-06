<?php

/*
 *  register.php
 *  
 *  A form which posts to register-exec.php with the details required for creating 
 *  a new user.
 */

$redirect_page = "register";
require($_SERVER['DOCUMENT_ROOT'].'/auth/auth.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/config/config.php');

//Ensure that the user has exec level or above
if (!auth_register_user()) {
  print_and_exit("You do not have permission to register users.");
}
?>

<h1>New Member Registration</h1>
<br />
<div class="ctext8">
  To use this page to register a new user, enter their e-mail address, name, and optionally 
  a comment and click "Register New Member". An automated message will be sent to their e-mail, 
  containing a temporary password, instructions for completing registration, and the comment 
  (if one was provided).
  <br /><br />
</div>
<form action="/users/register-exec.php" method="POST">
  <table>
    <tr>
      <th>E-mail</th>
      <td><input type="text" name="email" maxlength="255" /></td>
    </tr>
    <tr class="alt" >
      <th>First name</th>
      <td><input type="text" name="first_name" maxlength="64" /></td>
    </tr>
    <tr>
      <th>Last name</th>
      <td><input type="text" name="last_name" maxlength="64" /></td>
    </tr>
    <tr class="alt" >
      <th>Custom message</th>
      <td><input type="text" name="comment" maxlength="255" /></td>
    </tr>
    <tr>
      <th></th>
      <td style="text-align:center"><input style="width:150px" type="submit" value="Register New Member" /></td>
    </tr>
  </table>
</form>
<br /><br /><br />
<div class="ctext8">
  The e-mail sent will look like this:
  <br /><br /><br />
  From: Warriors Band &lt;<?php echo $email_username;?>&gt;<br />
  To: &lt;THEIR_EMAIL&gt;<br />
  Subject: <?php echo registration_email_subject(); ?><br />
  <br />
  <?php echo nl2br(registration_email_message("&lt;TEMPORARY PASSWORD&gt;",$_SESSION['first_name'],"&lt;COMMENT&gt;")); ?>
</div>
