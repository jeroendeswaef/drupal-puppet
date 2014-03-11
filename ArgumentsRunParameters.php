<?php

include_once("RunParameters.php");

class ArgumentsRunParameters implements RunParameters {
    function isDryRun() {
    	$dryRunOpt = getOpt("d:");
    	if (in_array('d', $dryRunOpt)) {
    		return true;	
    	}
        return false;
    }

} 