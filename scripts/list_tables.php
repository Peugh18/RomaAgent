<?php
$tables = array_map(function($t) { return array_values((array)$t)[0]; }, DB::select('SHOW TABLES'));
echo json_encode($tables);
