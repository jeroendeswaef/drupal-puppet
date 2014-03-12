<?php
/**
 * This action ensures that a particular directory is owen by a particular user/group
 * and that the directory is writable by that user (and nobody else).
 */

include_once("ApplicableState.php");


class WritableDirState extends ApplicableStateAbstract {
	const DIR_PERMISSIONS = 0711;

    function __construct(RunParameters $runParameters, $dirName, $userName, $groupName, $log) {
        parent::__construct($runParameters);
        $this->log = $log;
        $this->dirName = $dirName;
        $this->userName = $userName;
        $this->groupName = $groupName;

    	$perms = decoct(fileperms($this->dirName) & 0777);
        $this->doSetPerms = ($perms == decoct(self::DIR_PERMISSIONS) ? false : true);

        $fileOwner = posix_getpwuid(fileowner($dirName))['name'];
        $this->doSetUserName = ($fileOwner == $userName ? false : true);

        $fileGroup = posix_getpwuid(filegroup($dirName))['name'];
        $this->doSetGroup = ($fileGroup == $groupName ? false : true);
    }

    function isActionRequired() {
    	return ($this->doSetPerms or $this->doSetUserName or $this->doSetGroup);
    }

    function logAction() {
    	if ($this->doSetPerms) {
	        $this->log->addInfo('Changing the permissions of directory to be writable', [$this->dirName]);
	    }
	    if ($this->doSetUserName) {
	        $this->log->addInfo('Changing the file owner of directory', [$this->dirName, $this->userName]);
	    }
	    if ($this->doSetGroup) {
	        $this->log->addInfo('Changing the file group owner of directory', [$this->dirName, $this->groupName]);
	    }
    }

    function doAction() {
    	if ($this->doSetPerms) {
    		chmod($this->dirName, self::DIR_PERMISSIONS);
    	}
	    if ($this->doSetUserName) {
	    	chown($this->dirName, $this->userName);
	    }
	    if ($this->doSetGroup) {
	    	chgrp($this->dirName, $this->groupName);
	    }
    }
}

