<?php
include 'includes/Template.php';
use Templates\Template;

// instantiate the test class and add a variable
$tpl = new Template('tpls/test');
$tpl->addVariable('test', 10);
$tpl->run();

echo $tpl->getHTML(); // echo the html
?>