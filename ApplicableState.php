<?php

abstract class ApplicableStateAbstract {
    function __construct(RunParameters $runParameters) {
        $this->isDryRun = $runParameters->isDryRun();
    }

    function dryRun() {
        if ($this->isActionRequired()) {
            $this->logAction();
        }
    }

    function apply() {
        if ($this->isActionRequired()) {
            $this->logAction();
            $this->doAction();
        }
    }

    function execute() {
        if ($this->isDryRun) {
            $this->dryRun();
        } else {
            $this->apply();
        }
    }

    abstract function isActionRequired();
    abstract function logAction();
    abstract function doAction();
} 