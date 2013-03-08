<?php

/*
 *  albums/albumlist.php
 *
 *  Lists all photo albums.
 */

require_once($_SERVER['DOCUMENT_ROOT'].'/auth/auth-functions.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/config/config.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/config/display.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/config/database.php');

$no_albums = FALSE;

// Get all photo album information
$result = $mysqli->query(
  "SELECT `album_id`,`title`,`description` " .
  "FROM `photo_albums` " .
  "ORDER BY `date_uploaded`");
handle_sql_error($mysqli);
if ($result->num_rows == 0) {
  $no_albums = TRUE;
}
?>

<h1>Photo Albums</h1>
<br />
<?php
if(auth_view_photos()) {
?>
<br />
<?php
  if ($no_albums == TRUE) {
?>
<div class="center">
  There are currently no photo albums.
</div>
<?php
  } else {
?>
<ul>
<?php
    while ($album_row = $result->fetch_assoc()) {
      $path_suffix = $album_row['album_id'] . "/thumbs/0000.jpg";
      $image_preview_path = $photo_album_rel_path . "/" . $path_suffix;
      list($width, $height, $image_type) = getimagesize($photo_album_abs_path . $path_suffix);
      $album_link = $domain . '?page=album&amp;album_id=' . $album_row['album_id'];
?>
  <li class="imageborder" style="width:<?php echo $width ?>">
    <a href="<?php echo $album_link ?>">
      <img src="<?php echo $image_preview_path ?>"/>
    </a>
    <br />
    <a href="<?php echo $album_link ?>">
      <span style="font: 1.2em Arial, Helvetica, sans-serif">
        <?php echo $album_row['title'] ?>
      </span>
    </a>
    <br />
    <?php echo $album_row['description'] ?>
<?php
      if (auth_delete_photos()) { ?>
    <br />
    <form action="/albums/deletealbum-exec.php" method="POST">
      <input type="hidden" name="album_id" value="<?php echo $album_row['album_id'] ?>" />
<?php
        if ((isset($_GET['msg'])) && ($_GET['msg'] == "confirmdelete")) {
?>
      <input type="hidden" name="confirm" value="true" />
<?php
        }
?>
      <input style="width:100px" type="submit" value="Delete" />
    </form>
<?php
      }
?>
  </li>
<?php
      $result->free();
    }
?>
</ul>
<?php
  }
} else {
?>
<div class="center">
  You are not authorized to view photo albums.
</div>
<?php
}
?>
