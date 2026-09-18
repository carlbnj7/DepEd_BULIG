<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../app/bootstrap.php';
echo "BULIG first administrator setup\nAdministrator ID: ";$id=trim(fgets(STDIN));echo 'Name: ';$name=trim(fgets(STDIN));echo 'Password (8–72 characters; input is visible in this local terminal): ';$password=rtrim(fgets(STDIN),"\r\n");
if(!preg_match('/^[A-Za-z0-9_-]{3,30}$/',$id)||strlen($name)<2||strlen($name)>150||strlen($password)<8||strlen($password)>72){fwrite(STDERR,"Invalid ID, name, or password.\n");exit(1);}
try{db()->beginTransaction();q("INSERT INTO users(public_id,role,name,password_hash) VALUES(?,'admin',?,?)",[$id,$name,password_hash($password,PASSWORD_DEFAULT)]);q('INSERT INTO admins VALUES(?)',[db()->lastInsertId()]);db()->commit();echo "Administrator created. Open http://localhost/bulig/public/?page=login&role=admin\n";}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();fwrite(STDERR,"Could not create account. Check configuration, schema import, and whether the ID already exists.\n");exit(1);}
