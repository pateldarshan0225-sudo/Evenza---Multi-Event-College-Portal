<?php
$conn = mysqli_connect("localhost", "root", "", "evenza");

if (!$conn) 
{
    die("Connection Failed: " . mysqli_connect_error());
}
?>