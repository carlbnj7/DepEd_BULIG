<?php
/* Make an administrator account from the command line (CREATE-ADMIN.bat on Windows).
   The Administrator ID is made automatically (A + year + number, for example A260001). */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../app/bootstrap.php';
echo "BULIG administrator setup\nName: ";$name=trim((string)fgets(STDIN));echo 'Password (8–72 characters; input is visible in this local terminal): ';$password=rtrim((string)fgets(STDIN),"\r\n");
if(strlen($name)<2||strlen($name)>150||strlen($password)<8||strlen($password)>72){fwrite(STDERR,"Invalid name or password.\n");exit(1);}
try{
 db()->beginTransaction();$id=new_account_id('admin');
 q("INSERT INTO users(public_id,role,name,password_hash) VALUES(?,'admin',?,?)",[$id,$name,password_hash($password,PASSWORD_DEFAULT)]);q('INSERT INTO admins VALUES(?)',[db()->lastInsertId()]);db()->commit();
 echo "Administrator created.\nAdministrator ID: $id\nOpen http://localhost/bulig/public/?page=login&role=admin and sign in with this ID. You will make a 4-digit PIN at the first sign-in.\n";
}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();fwrite(STDERR,'Could not create the administrator: '.$e->getMessage()."\n");exit(1);}
