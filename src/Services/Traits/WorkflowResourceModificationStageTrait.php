<?php

namespace HamisJuma\Workflow\Services\Traits;

use Illuminate\Database\Eloquent\Model;

trait WorkflowResourceModificationStageTrait
{
    public function canEditResource(Model $model, $pending_level, $wf_definition)
    {
        $allow = false;
        switch ($model->getTable()) {
            /*requisitions Module*/
            case 'requisitions':
                switch ($pending_level) {
                    case 1: //Applicant level
                        if ($model->user_id == access()->id()) {
                            $allow = true;
                        }
                        break;
                        //                    case 3: //Level 3
                        //                        if(access()->hasWorkflowDefinitionProvided($wf_definition)){
                        //                            $allow = true;
                        //                        }
                        //                        break;
                }
                break;

                /*Performance Report Module*/
            case 'pr_reports':
                switch ($pending_level) {
                    case 1: //Applicant level
                        if ($model->user_id == access()->id()) {
                            $allow = true;
                        }
                        break;
                }
                break;

            case 'hr_user_hire_requisition_job_shortlister_requests':
                switch ($pending_level) {
                    case 1: //Applicant level
                        if ($model->user_id == access()->id()) {
                            $allow = true;
                        }
                        break;
                }
                break;
            case 'hr_hire_requisitions':
                switch ($pending_level) {
                    case 1: //Applicant level
                        if ($model->user_id == access()->id()) {
                            $allow = true;
                        }
                        break;
                }
                break;
            case 'hr_interview_workflow_reports':
                switch ($pending_level) {
                    case 1: //Applicant level
                        if ($model->user_id == access()->id()) {
                            $allow = true;
                        }
                        break;
                }
                break;
            case 'payments':
                switch ($pending_level) {
                    case 1: //Applicant level
                        if ($model->user_id == access()->id()) {
                            $allow = true;
                        }
                        break;
                }
                break;
            case 'activity_reports':
                switch ($pending_level) {
                    case 1: //Applicant level
                        if ($model->user_id == access()->id()) {
                            $allow = true;
                        }
                        break;
                    case 3: //Finance officer level
                        if ($model->payment != null and $model->payment->payment->user_id == access()->id()) {
                            $allow = true;
                        }

                        break;
                }
                break;
            case 'safari_advances':
                switch ($pending_level) {
                    case 1: //Applicant level
                        if ($model->user_id == access()->id()) {
                            $allow = true;
                        }
                        break;
                    case 3: //Finance officer level
                        if ($model->safariAdvancePayment != null and $model->safariAdvancePayment->payment->user_id == access()->id()) {
                            $allow = true;
                        }
                        break;
                }
                break;
            case 'program_activities':
                switch ($pending_level) {
                    case 1: //Applicant level
                        if ($model->user_id == access()->id()) {
                            $allow = true;
                        }
                        break;
                }
                break;
            case 'retirements':
                switch ($pending_level) {
                    case 1: //Applicant level
                        if ($model->user_id == access()->id()) {
                            $allow = true;
                        }
                        break;
                }
                break;
            case 'leaves':
                switch ($pending_level) {
                    case 1: //Applicant level
                        if ($model->user_id == access()->id()) {
                            $allow = true;
                        }
                        break;
                }
                break;
            case 'hr_hire_advertisement_requisitions':
                switch ($pending_level) {
                    case 1: //Applicant level
                        if ($model->user_id == access()->id()) {
                            $allow = true;
                        }
                        break;
                }
                break;
            case 'requisition_procurement_closures':
                switch ($pending_level) {
                    case 1: //Applicant level
                        if ($model->user_id == access()->id()) {
                            $allow = true;
                        }
                        break;
                }
                break;
            case 'requisition_traveling_cost_vendor_closures':
                switch ($pending_level) {
                    case 1: //Applicant level
                        if ($model->user_id == access()->id()) {
                            $allow = true;
                        }
                        break;
                }
                break;
        }
        return $allow;
    }

    public function canUpdateAttributeRateResource(Model $model, $pending_level, $wf_module_id)
    {
        $allow = false;
        switch ($wf_module_id) {
            case 11:
            case 21:
            case 22:
            case 23:
            case 24:
                switch ($pending_level) {
                    case 1:
                        if ($model->user_id == access()->id()) {
                            $allow = true;
                        }
                        // no break
                    case 2:
                        if ($model->supervisor_id == access()->id()) {
                            $allow = true;
                        }
                        if ($model->parent->supervisor_id  == access()->id()) {
                            $allow = true;
                        }
                        break;
                }
                break;
        }
        return $allow;
    }
}
