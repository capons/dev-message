<?php
# THIS FILE SETS THE S3 BUCKET SETTINGS

# include the standard S3 class
if (!class_exists('S3')) require_once('S3.php');
		
# instantiate the class with the access details
$s3 = new S3(S3_ACCESS_KEY, S3_ACCESS_SECRET);
# put the bucket to which the files for this system will be going
$s3->putBucket(S3_BUCKET_NAME, S3::ACL_PUBLIC_READ);

?>