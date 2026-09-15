<?php

$servername='localhost';
$username='root';
$senha='';
$db="aw2_2";
$erro="null";
try {
    $conn=new PDO("mysql:host=$servername;dbname=$db",$username,$senha);
    $conn->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    $erro=$e->getMessage();
    echo $erro;
}

?>