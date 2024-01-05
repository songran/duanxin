<?php

$arr = array(

	 "type"      => 1,
     "phone"     => (string)'1342233222',
     "tplId"     => (string)'2',
     "tplParams" => json_encode(array('hello word')) ,
);


echo json_encode($arr);

