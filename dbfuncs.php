<?php

// Hide all error reporting (keeps vulnerabilities, hides stack traces)
error_reporting(0);
ini_set('display_errors', 0);


require_once 'common.php';
require_once 'consts.php';

$con = null;

function connect()
{
    global $con;
    $con = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_DB);

    if (!$con)
        die("Unable to connect to: " . DB_USER . ":" . DB_PASS . "@" . DB_HOST . ". Error: " . mysqli_connect_error());
}

function getSelect($query)
{
    global $con;
    if ($con === null)
        connect();

    $result = mysqli_query($con, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $rows = array();
        while ($row = mysqli_fetch_row($result)) {
            $rows[] = $row;
        }
        return $rows;
    }

    return null;
}

function insertQuery($query, $update = false)
{
    global $con;
    if ($con === null)
        connect();

    $result = mysqli_query($con, $query);

    if (!$result) {
        return false;
    }

    return ($update === false) ? true : mysqli_insert_id($con);
}