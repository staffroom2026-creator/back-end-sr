<?php 
// Simple page redirect
function redirect($page){
    header('location: ' . URLROOT . '/' . $page);
}


 function redirectx($urlx,$page){
    header('location: ' . $urlx . '/' . $page);
}

?>