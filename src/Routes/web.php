<?php

Route::get('defaults', 'WorkflowController@defaults')->name('defaults');
Route::get('pending', 'WorkflowController@pending')->name('pending');
Route::get('pending/get', 'WorkflowController@getPending')->name('pending.get');
Route::get('mypending', 'WorkflowController@myPending')->name('mypending');
Route::get('attended', 'WorkflowController@attended')->name('attended');
Route::get('new', 'WorkflowController@newWorkflow')->name('new');
Route::get('new/get', 'WorkflowController@getNewWf')->name('new.get');
Route::patch('update/{definition}', 'WorkflowController@updateDefinitionUsers')->name('update');
Route::get('get_users/{definition}', 'WorkflowController@getUsers')->name('get_users');
Route::get('ended', 'WorkflowController@ended')->name('ended');
Route::get('holded', 'WorkflowController@holded')->name('holded');
Route::get('Fget_wf_tracks/{resource_id}/{wf_module_group_id}/{type}', 'WorkflowController@getWfTracksForDatatable')->name('wf_tracks.get');
Route::post('get_completed_wf_tracks/{resource_id}/{wf_module_group_id}/{type}', 'WorkflowController@getCompletedWfTracks')->name('wf_completed_tracks.get');
Route::get('get_deactivated_wf_tracks/{resource_id}/{wf_module_group_id}', 'WorkflowController@getDeactivatedWfTracksForDataTable')->name('wf_tracks.get_deactivated');
Route::get('get_deactivated_claim_wf_tracks/{resource_id}', 'WorkflowController@getDeactivatedClaimWfTracksForDataTable')->name('wf_tracks.get_deactivated_claim');
Route::get('workflow_modal_content/', 'WorkflowController@getWorkflowModalContent')->name('workflow_modal_content');
Route::get('workflow_content/', 'WorkflowController@getWorkflowTrackContent')->name('workflow_content');
Route::post('update_workflow/{wf_track}', 'WorkflowController@updateWorkflow')->name('update_workflow');
Route::get('wf_definitions_for_select', 'WorkflowController@getWfDefinitionsByWfModuleForSelect')->name('wf_definitions_for_select');
Route::get('initiate_workflow/{resource_id}/{group}/{type}', 'WorkflowController@initiateWorkflow')->name('initiate_workflow');
Route::post('assign-to-wf-track/{wf_track}', 'WorkflowController@assignToWorkflow')->name('assign.task');
Route::get('view', 'WorkflowController@workflowAdminView')->name('view');
Route::get('all/pendings', 'WorkflowController@allPendingsDatatables')->name('pending.levels');
Route::get('all/pending-coc-to-approve/{wf_definition_id}/{status}', 'WorkflowController@getWorkfolwPendingByPrevWfDatatables')->name('pending.previous.levels');
Route::get('pending/module/{wf_definition}/{status}', 'WorkflowController@reParticipatedWorkflow')->name('reparticipate');
Route::get('responded', 'WorkflowController@respondedWorkflow')->name('responded');
Route::get('workflow_group/certificate', 'WorkflowController@getCertificationWorkflowGroups')->name('get_certification_wf_groups');
Route::get('workflow_group/imports', 'WorkflowController@getImportWorkflowGroups')->name('get_import_wf_groups');
Route::get('permit-approval', 'WorkflowController@permitApproval')->name('permit_approval');
Route::get('permit-approval/get', 'WorkflowController@getPermitApproval')->name('permit_approval.get');
Route::post('{wf_track}/recall', 'WorkflowController@recall')->name('recall');
Route::post('{wf_track}/resume-from-wf-done', 'WorkflowController@resumeFromWfDone')->name('resume_from_wf_done');
Route::group(['prefix' => 'user-assignment', 'as' => 'user_assignment.'], function () {
Route::post('{wf_track}/assign', 'WfTrackUserController@assignment')->name('assign');
});
Route::get('sidebar-content', 'WorkflowController@getSidebarContent')->name('sidebar.content');