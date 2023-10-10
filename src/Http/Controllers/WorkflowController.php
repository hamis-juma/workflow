<?php

namespace HamisJuma\Workflow\Http\Controllers;

//use App\DataTables\WorkflowTrackDataTable;
use HamisJuma\Workflow\Exceptions\GeneralException;
use HamisJuma\Workflow\Exceptions\WorkflowException;
use HamisJuma\Workflow\Http\Requests\UpdateWorkflowRequest;
use HamisJuma\Workflow\Models\WfTrack;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use HamisJuma\Workflow\Repositories\WfModuleGroupRepository;
use HamisJuma\Workflow\Repositories\WfDefinitionRepository;
//use HamisJuma\Workflow\Repositories\Access\UserRepository;
use HamisJuma\Workflow\Models\WfDefinition;
use HamisJuma\Workflow\Repositories\WfModuleRepository;
use HamisJuma\Workflow\Repositories\WfTrackRepository;
use HamisJuma\Workflow\Services\Workflow;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
//use Yajra\Datatables\Datatables;


class workflowController extends Controller
{
    /**
     *WORKFLOW CONTROLLER
     * @developer Hamis Hamis
     */



    /**
     * @var
     */
    protected $moduleGroup;

    /**
     * @var WfDefinition
     */
    protected $definitions;

    /**
     * @var wf tracks
     */
    protected $wf_tracks;

    /**
     * @var
     */
    protected $users;

    protected $wf_modules;

    /**
     * workflowController constructor.
     * @param WfModuleGroupRepository $moduleGroup
     * @param WfDefinitionRepository $definitions
     * @param UserRepository $users
     */
    public function __construct(WfModuleGroupRepository $moduleGroup, WfDefinitionRepository $definitions, /*UserRepository $users*/)
    {
        /* $this->middleware('access.routeNeedsPermission:assign_workflows'); */
        $this->middleware('auth');
        $this->moduleGroup = $moduleGroup;
        $this->definitions = $definitions;
        $this->users = [];
        $this->wf_tracks = new WfTrackRepository();
        $this->wf_modules = (new WfModuleRepository());
    }

    public function index()
    {
        dd("workflow works");
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function defaults()
    {
        return view('backend.system.workflow.defaults')
            ->withGroups($this->moduleGroup->getAll())
            ->withUsers($this->users->getAll());
    }

    /**
     * @param WfDefinition $definition (Workflow definition id)
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUsers(WfDefinition $definition)
    {
        return response()
            ->json($definition->users->pluck("id")->all());
    }

    public function updateDefinitionUsers(WfDefinition $definition)
    {
        $this->definitions->updateDefinitionUsers($definition, ['users' => request()->input('users')]);
        return response()
            ->json(['success' => true]);
    }

    /**
     * @param $resource_id
     * @param $wf_module_group_id
     * @param $type
     * @return $this
     * @throws \App\Exceptions\GeneralException
     */
    public function getCompletedWfTracks($resource_id, $wf_module_group_id, $type)
    {
        $wf_tracks = $this->wf_tracks->getCompletedWfTracks($resource_id, $wf_module_group_id, $type);
        $module = (new WfModuleRepository())->getModuleInstance(['wf_module_group_id' => $wf_module_group_id, 'type' => $type]);
        return view("backend.includes.workflow.completed_tracks")
            ->with("wf_tracks", $wf_tracks)
            ->with("module", $module);
    }

    /**
     * @param $resource_id
     * @param $wf_module_group_id
     * @param $type
     * @return mixed
     * @throws \App\Exceptions\GeneralException
     * @throws \Exception
     */
    public function getWfTracksForDatatable($resource_id, $wf_module_group_id, $type)
    {
        $data = $this->wf_tracks->getPendingWfTracksForDatatable($resource_id, $wf_module_group_id, $type);
//        return Datatables::of($this->wf_tracks->getPendingWfTracksForDatatable($resource_id, $wf_module_group_id, $type))
        return Datatables::of($data)
            ->editColumn('user_id', function($wf_track) {
                return $wf_track->username_formatted;
            })
            ->editColumn('receive_date', function ($wf_track) {
                return $wf_track->receive_date_formatted;
            })
            ->editColumn('forward_date', function ($wf_track) {
                return $wf_track->forward_date_formatted;
            })
            ->editColumn('status', function ($wf_track) {
                return $wf_track->status_narration;
            })
            ->editColumn('wf_definition_id', function ($wf_track) {
                return $wf_track->wfDefinition->level;
            })
            ->addColumn('action', function ($wf_track) {
                return $wf_track->status_narration;
            })
            ->addColumn('description', function ($wf_track) {
                return $wf_track->wfDefinition->description;
            })
            ->addColumn("aging", function ($wf_track) {
                return $wf_track->getAgingDays();
            })
            /*->addColumn("option", function ($wf_track) {
                return $wf_track->action_button;
            })*/
            ->rawColumns(['user_id'])
            ->make(true);
    }

    public static function getWfTracks($resource_id, WorkflowTrackDataTable $dataTable)
    {
        $dataTable->with('resource_id', $resource_id)->render('backend.includes.workflow_track');
    }

    /**
     * @param $resource_id
     * @param $wf_module_group_id
     * @return mixed
     * @throws \Exception
     */
    public function getDeactivatedWfTracksForDataTable($resource_id, $wf_module_group_id) {

        return Datatables::of($this->wf_tracks->getDeactivatedWfTracksForDataTable($resource_id, $wf_module_group_id))
            ->editColumn('user_id', function($wf_track) {
                return $wf_track->username_formatted;
            })
            ->editColumn('receive_date', function ($wf_track) {
                return $wf_track->receive_date_formatted;
            })
            ->editColumn('forward_date', function ($wf_track) {
                return !is_null($wf_track->forward_date) ? $wf_track->forward_date_formatted : ' ';
            })
            ->editColumn('status', function ($wf_track) {
                return $wf_track->status_narration;
            })
            ->editColumn('wf_definition_id', function ($wf_track) {
                return $wf_track->wfDefinition->level;
            })
            ->addColumn('action', function ($wf_track) {
                return $wf_track->status_narration;
            })
            ->addColumn('description', function ($wf_track) {
                return $wf_track->wfDefinition->description;
            })
            ->addColumn("aging", function ($wf_track) {
                return $wf_track->getAgingDays();
            })
            ->rawColumns(['user_id'])
            ->make(true);
    }

    /*
   * Get Deactivated Claim workflow Tracks for this resource id
   */
    public function getDeactivatedClaimWfTracksForDataTable($id) {

        return Datatables::of($this->wf_tracks->getDeactivatedClaimWfTracksForDataTable($id))
            ->editColumn('user_id', function($wf_track) {
                return $wf_track->username_formatted;
            })
            ->editColumn('receive_date', function ($wf_track) {
                return $wf_track->receive_date_formatted;
            })
            ->editColumn('forward_date', function ($wf_track) {
                return !is_null($wf_track->forward_date) ? $wf_track->forward_date_formatted : ' ';
            })
            ->editColumn('status', function ($wf_track) {
                return $wf_track->status_narration;
            })
            ->editColumn('wf_definition_id', function ($wf_track) {
                return $wf_track->wfDefinition->level;
            })
            ->addColumn('action', function ($wf_track) {
                return $wf_track->status_narration;
            })
            ->addColumn('description', function ($wf_track) {
                return $wf_track->wfDefinition->description;
            })
            ->addColumn("aging", function ($wf_track) {
                return $wf_track->getAgingDays();
            })
            ->rawColumns(['user_id'])
            ->make(true);
    }

    /**
     * @return mixed
     * @throws \App\Exceptions\GeneralException
     */
    public function getWorkflowModalContent()
    {
        $wf_track_id = request()->input("wf_track_id");
        $wf_track = $this->wf_tracks->find($wf_track_id);
        $resource_id = $wf_track->resource_id;
        $workflow = new Workflow(['wf_module_id' => $wf_track->wfDefinition->wfModule->id, 'resource_id' => $resource_id]);
        $wf_module = $wf_track->wfDefinition->wfModule;
        $wf_module_group_id = $wf_module->wf_module_group_id;
        $type = $wf_module->type;
        $assignStatus = $this->wf_tracks->assignStatus($wf_track_id);
        $wf_definition = $wf_track->wfDefinition;

        /*Action description*/
        $approved = $wf_track->wfDefinition->action_description;
        $statuses['0'] = '';
        if ($wf_definition->is_approval) {
            $statuses['1'] = $approved;
        } else {
            $statuses['1'] = $approved;
        }
        if ($workflow->currentLevel() <> 1) {
            $prevWfDefinition = $workflow->nextWfDefinition(-1, true);
            if ($prevWfDefinition->allow_rejection) {
                $statuses['2'] = "Reverse to level";
            }
        }
        /*end action*/
        return view("system/workflow/modal/Approval_model")
            ->with("assign_status", $assignStatus)
            ->with("wf_track", $wf_track)
            ->with("has_participated", $workflow->hasParticipated())
            ->with("user_has_access", $workflow->userHasAccess(access()->id(), $workflow->currentLevel()))
            ->with("previous_levels", $workflow->previousLevels())
            ->with("statuses", $statuses)
            ->with('has_to_assign', $workflow->hasToAssign());

    }



    /**
     * @return mixed
     * @throws \App\Exceptions\GeneralException
     * Get workflow tracks contents
     */
    public function getWorkflowTrackContent()
    {
        $wf_track_id = request()->input("wf_track_id");
        $wf_track = $this->wf_tracks->find($wf_track_id);
        $resource = $wf_track->resource;
        $wf_done = isset($resource->wf_done) ? $resource->wf_done : 0;
        $resource_id = $wf_track->resource_id;
        $workflow = new Workflow(['wf_module_id' => $wf_track->wfDefinition->wfModule->id, 'resource_id' => $resource_id]);
        $previous_wf_track = $workflow->previousWfTrack();
        $wf_module = $wf_track->wfDefinition->wfModule;
        $wf_module_group_id = $wf_module->wf_module_group_id;
        $wf_definition = $wf_track->wfDefinition;
        $type = $wf_module->type;
//               $assignStatus = $this->wf_tracks->assignStatus($wf_track_id);
        $has_to_assign = $wf_definition->assign_next_user;
        /*Next users to assign*/
        $next_users = ($wf_definition->assign_next_user == 1) ? $this->wf_tracks->getNextUsersToAssignWf($wf_track) : [];
        /*end next users*/
        /*wf tracks*/
        $completed_tracks = $this->wf_tracks->getCompletedWfTracks($resource_id, $wf_module_group_id, $type)->get();
        $pending_tracks = $this->wf_tracks->getPendingWfTracksForDatatable($resource_id, $wf_module_group_id, $type);
        /*end tracks*/
        /*Action description*/
        $approved = $wf_track->wfDefinition->action_description;
        $status_description = $wf_track->wfDefinition->status_description;
        $statuses[''] = 'select';
        if ($wf_definition->is_approval) {
            $statuses['1'] = $approved;
            $statuses['11'] = $status_description;
        } else {
            $statuses['1'] = $approved;
            $statuses['11'] = $status_description;
        }

        if ($workflow->currentLevel() <> 1) {
            $prevWfDefinition = $workflow->nextWfDefinition(-1, true);
            if ($prevWfDefinition->allow_rejection) {
                $statuses['2'] = "Reverse to level";
                $statuses['5'] = "Reject";
            }
        }

        /*end action*/
        return view("includes.workflow.workflow_contents")
//            ->with("assign_status", $assignStatus)
            ->with("wf_track", $wf_track)
            ->with("has_participated", $workflow->hasParticipated())
            ->with("user_has_access", $workflow->userHasAccess(access()->id(), $workflow->currentLevel()))
            ->with("previous_levels", $workflow->previousLevels())
            ->with("statuses", $statuses)
            ->with('has_to_assign', $has_to_assign)
            ->with('completed_tracks', $completed_tracks)
            ->with('pending_tracks', $pending_tracks)
            ->with('wf_done',$wf_done)
            ->with('next_users', $next_users)
            ->with('previous_wf_track', $previous_wf_track);
    }


    /**
     * @param WfTrack $wf_track
     * @param UpdateWorkflowRequest $request
     * @return mixed
     * @throws GeneralException
     * @throws WorkflowException
     */
    public function updateWorkflow(WfTrack $wf_track, UpdateWorkflowRequest $request)
    {
        /*check if user has access right*/
        if ($wf_track->status == 1) {
            throw new WorkflowException(__('notifications.workflow.error'));
        }
        $this->checkIfUserHasAccessOnUpdateWf($wf_track);
        $action = $request->input("action");
        $option_array = [];
        switch ($action) {
            case 'assign':
                $input = ['user_id' => access()->id(), 'assigned' => 1];
                $success = true;
                $message = trans('alerts.backend.workflow.assigned');
                break;
            case 'approve_reject':
                /*Status*/
                $status = $request->input("status");
                switch ($status) {
                    case 3: //held
                        $status = 3;
                        $option_array['completed'] = 3;
                        break;
                    case 4: //ended
                        $status = 4;
                        $option_array['completed'] = 4;
                        break;
                    case 5: //rejected
                        $status = 5;
                        $option_array['completed'] = 5;
                        break;
                    default:
                        $status = $status;
                        break;
                }

                /*end status*/
                $region_id = isset($wf_track->resource->region_id) ? $wf_track->resource->region_id : null; //get port id for resource
                $input = ['user_id' => access()->id(), 'next_user_id'=>request()->input("user"), 'status' => $status, 'comments' => $request->input("comments"), 'forward_date' => Carbon::now(), 'region_id' => $region_id];
                if ($status == '2') {
                    $input['level'] = (string) $request->input("level");
                }

                $success = true;
                $message = trans('alerts.backend.workflow.updated');
                break;
        }

        $input = array_merge($input, $option_array );
        //Heavy Duty Call
        $this->wf_tracks->updateWorkflow($wf_track, $input, $action);
        alert()->success(__('notifications.workflow.participate'), __('notifications.workflow.title'));
        //redirect if not resource owner
        if($wf_track->resource->user_id != access()->id()){
            return redirect()->route('workflow.new','wf_module_id='.$wf_track->wfDefinition->wf_module_id);
        }
        return redirect()->back();
    }

    /**
     * User assign a task
     * @param WfTrack $wf_track
     * @return \Illuminate\Http\JsonResponse
     * @throws \App\Exceptions\GeneralException
     */
    public function assignToWorkflow(WfTrack $wf_track)
    {
        $action = 'assign';
        $input = ['user_id' => access()->id(), 'assigned' => 1];
        $success = true;
        $message = trans('label.assign');
        $this->wf_tracks->updateWorkflow($wf_track, $input, $action);
        /*Update Application Number*/
        if ($wf_track->application->application_number == null) {
            if (access()->user()->roles()->first()->id == 6) {
                $application = new ApplicationRepository();
                $application->updateApplicationNumber($wf_track->resource_id);
            }
        }
//        return response()->json(['success' => $success, 'message' => $message, 'action' => $action]);
        return redirect()->back()->withFlashSuccess(__('label.assign_task'));
    }

    public function newWorkflow()
    {
        $wf_module_groups = new WfModuleGroupRepository();
        $wf_modules = new WfModuleRepository();
//        dd($wf_modules->getNewActiveUser());
//        dd($wf_modules->getNewActiveUser());
        return view("system.workflow.new")
            ->with("wf_modules", $wf_modules->getAllActive()->pluck('name', 'id')->all())
            ->with("group_counts", $wf_modules->getNewActiveUser())
            ->with("state", "new")
            ->with("statuses", ['2' => 'All', '1' => 'Assigned to Me', '0' => 'Not Assigned', '3' => 'Assigned to User'])
            ->with("unregistered_modules", $wf_modules->unregisteredMemberNotificationIds());
            // ->with("users", $this->users->query()->where("id", "<>", access()->id())->get()->pluck('name', 'id'));
    }


    public function pending()
    {
        $wf_module_groups = new WfModuleGroupRepository();
        $wf_modules = new WfModuleRepository();
        return view("system/workflow/pending")
            ->with("wf_modules", $wf_modules->getAllActive()->pluck('name', 'id')->all())
            ->with("group_counts", $wf_modules->getActiveUser())
            ->with("state", "all")
            ->with("statuses", ['2' => 'All', '1' => 'Assigned to Me', '0' => 'Not Assigned', '3' => 'Assigned to User'])
            ->with("unregistered_modules", $wf_modules->unregisteredMemberNotificationIds());
            // ->with("users", $this->users->query()->where("id", "<>", access()->id())->get()->pluck('name', 'id'));
    }

    public function getPending()
    {
        $datatables = $this->wf_tracks->getForWorkflowDatatable();

        return $datatables->make(true);
    }

    public function myPending()
    {

        $wf_module_groups = new WfModuleGroupRepository();
        $wf_modules = new WfModuleRepository();
        return view("system/workflow/my_pending")
            ->with("wf_modules", $wf_modules->getAllActive()->pluck('name', 'id', 'id')->all())
            ->with("group_counts", $wf_modules->getActiveUser())
            ->with("unregistered_modules", $wf_modules->unregisteredMemberNotificationIds())
            ->with("state", "pending");
    }

    public function respondedWorkflow()
    {
        $wf_module_groups = new WfModuleGroupRepository();
        $wf_modules = new WfModuleRepository();
        return view("system/workflow/new")
            ->with("wf_modules", $wf_modules->getAllActive()->pluck('name', 'id')->all())
            ->with("group_counts", $wf_modules->getRespondedActiveUser())
            ->with("state", "responded")
            ->with("statuses", ['2' => 'All', '1' => 'Assigned to Me', '0' => 'Not Assigned', '3' => 'Assigned to User'])
            ->with("unregistered_modules", $wf_modules->unregisteredMemberNotificationIds());
            // ->with("users", $this->users->query()->where("id", "<>", access()->id())->get()->pluck('name', 'id'));
    }

    /**
     * Attended Workflow
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function attended()
    {
        $wf_module_groups = new WfModuleGroupRepository();
        $wf_modules = new WfModuleRepository();
        return view("system/workflow/new")
            ->with("wf_modules", $wf_modules->getAllActive()->pluck('name', 'id')->all())
            ->with("group_counts", $wf_modules->getMyAttendedActiveUser())
            ->with("state", "attended")
            ->with("statuses", ['1' => 'Attended by Me', '3' => 'Attended by User'])
            ->with("unregistered_modules", $wf_modules->unregisteredMemberNotificationIds());
            // ->with("users", $this->users->query()->where("id", "<>", access()->id())->get()->pluck('name', 'id'));
    }

    /**
     * Holded workflow
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function holded()
    {
        $wf_module_groups = new WfModuleGroupRepository();
        $wf_modules = new WfModuleRepository();
        return view("system.workflow.holded")
            ->with("wf_modules", $wf_modules->getAllActive()->pluck('name', 'id')->all())
            ->with("group_counts", $wf_modules->getMyHoldedActiveUser())
            ->with("state", "holded")
            ->with("statuses", ['1' => 'Attended by Me', '3' => 'Attended by User'])
            ->with("unregistered_modules", $wf_modules->unregisteredMemberNotificationIds())
            ->with("users", $this->users->query()->where("id", "<>", access()->id())->get()->pluck('name', 'id'));
    }

    /**
     * Ended Workflow
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function ended()
    {
        $wf_module_groups = new WfModuleGroupRepository();
        $wf_modules = new WfModuleRepository();
        return view("system.workflow.ended")
            ->with("wf_modules", $wf_modules->getAllActive()->pluck('name', 'id')->all())
            ->with("group_counts", $wf_modules->getMyEndedActiveUser())
            ->with("state", "ended")
            ->with("statuses", ['1' => 'Attended by Me', '3' => 'Attended by User'])
            ->with("unregistered_modules", $wf_modules->unregisteredMemberNotificationIds())
            ->with("users", $this->users->query()->where("id", "<>", access()->id())->get()->pluck('name', 'id'));
    }

    public function initiateWorkflow($resource_id, $group, $type)
    {

    }


    public function getUnsignedWorkflowDatatable()
    {
        $workflow = $this->wf_tracks->getUnsignedWorkFlowForDatatable();
        return DataTables::of($workflow)
            ->addIndexColumn()
            ->addColumn('type', function ($workflow) {
                return $workflow->application->category->name;
            })
            ->addColumn('no', function($workflow) {
                if ($workflow->application->application_number == null) {
                    return 'Waiting..';//TODO Change
                } else{
                    return $workflow->application->application_number;
                }
            })
            ->addColumn('bill_no', function($workflow) {
                return $workflow->application->manifest->mblno;
            })
            ->addColumn('application', function($workflow) {
                return $workflow->application->application_type_badge;
            })
            ->addColumn('company', function($workflow) {
                return $workflow->application->company->name;
            })
            ->addColumn('date', function ($workflow) {
                return $workflow->created_at;
            })
            ->addColumn('action', function ($workflow) {
                return '<a href="'.route('application.overview',$workflow->application->uuid).'" class="btn btn-xs btn-primary">View</a>';
            })
            ->rawColumns(['application','action'])
            ->make(true);
    }

    public function myPendings()
    {
        $workflow = $this->wf_tracks->getMypendingsapps();
        return DataTables::of($workflow)
            ->addIndexColumn()
            ->addColumn('type', function ($workflow) {
                return $workflow->application->category->name;
            })
            ->addColumn('no', function($workflow) {
                return $workflow->application->application_number;
            })
            ->addColumn('bill_no', function($workflow) {
                return $workflow->application->manifest->mblno;
            })
            ->addColumn('application', function($workflow) {
                return $workflow->application->application_type_badge;
            })
            ->addColumn('company', function($workflow) {
                return $workflow->application->company->name;
            })
            ->addColumn('date', function ($workflow) {
                return $workflow->created_at;
            })
            ->addColumn('action', function ($workflow) {
                return '<a href="'.route('application.overview',$workflow->application->uuid).'" class="btn btn-xs btn-primary">View</a>';
            })
            ->rawColumns(['application','action'])
            ->make(true);
    }

    /**
     * All Pending on each level
     * @param Request $request
     * @return mixed
     * @throws \Exception
     */
    public function allPendingsDatatables(/*Request $request*/)
    {
        $workflow = $this->wf_tracks->getPendingModule(1);
        return DataTables::of($workflow)
            ->addIndexColumn()
            ->addColumn('type', function ($workflow) {
                return $workflow->application->category->name;
            })
            ->addColumn('no', function($workflow) {
                return $workflow->application->application_number;
            })
            ->addColumn('bill_no', function($workflow) {
                return $workflow->application->bill_number;
            })
            ->addColumn('company', function($workflow) {
                return $workflow->application->company->name;
            })
            ->addColumn('date', function ($workflow) {
                return $workflow->created_at;
            })
            ->addColumn('action', function ($workflow) {
                return '<a href="'.route('application.overview',$workflow->application->uuid).'" class="btn btn-xs btn-primary">View</a>';
            })
            ->make(true);
    }

    public function workflowAdminView()
    {
        $wf_group = new WfModuleGroupRepository();
        $wf_groups = $wf_group->getWorkflowfGroup();
        $staff = new StaffRepository();
        $staffs = $staff->getAllRegisteredStaffs()->pluck('full_name','id');
        return view('includes.workflow.view_workflow',compact('wf_groups'));
    }

    /**
     * @param Model $wf_track
     * Check if user has access on update Wf
     * @throws GeneralException
     */
    public function checkIfUserHasAccessOnUpdateWf(Model $wf_track)
    {
        if(!($wf_track->checkIfHasRightCurrentWfTrackAction()))
        {
            /*User do not have access right for this level*/
            throw new GeneralException(__('exceptions.workflow.user_access_right'));
        }
    }


    /*Reparticipated Workflow*/
    public function reParticipatedWorkflow($wf_definition_id, $status)
    {
        return view('system.workflow.re_participate')
            ->with('title', (Sysdef::query()->where('reference','=','MENU'.$wf_definition_id.$status))->first())
            ->with('wf_definition', $wf_definition_id)
            ->with('status', $status);
    }


    /**
     * @param $wf_definition_id
     * @param $status
     * @return mixed
     * @throws \Exception
     * get Workfolw Pending By PrevWf Datatables
     */
    public function getWorkfolwPendingByPrevWfDatatables($wf_definition_id, $status)
    {
        $workflow = $this->wf_tracks->getWorkfolwByPrevWf($wf_definition_id, $status);
        return DataTables::of($workflow)
            ->addIndexColumn()
            ->addColumn('type', function ($workflow) {
                return $workflow->application->category->name;
            })
            ->addColumn('application_number', function($workflow) {
                return $workflow->application->application_number;
            })
            ->addColumn('bl_number', function($workflow) {
                return $workflow->application->bill_number;
            })
            ->addColumn("application", function ($workflow) {
                return $workflow->application->application_type_badge;
            })
            ->addColumn('company', function($workflow) {
                return $workflow->application->company->name;
            })
            ->addColumn('date', function ($workflow) {
                return $workflow->receive_date;
            })
            ->addColumn('action', function ($workflow) {
                return '<a href="'.route('application.overview',$workflow->application->uuid).'" class="btn btn-xs btn-primary">View</a>';
            })
            ->rawColumns(['application','action'])
            ->make(true);
    }

    /**
     * @param $wf_definition_id
     * @param $status
     * @return mixed
     * @throws \Exception
     * get Workfolw Pending By PrevWf Datatables
     */
    public function getPendingWorkflowByPrevWfDatatable($wf_definition_id, $status)
    {
        $workflow = $this->wf_tracks->getWorkfolwByPrevWf($wf_definition_id, $status);
        return DataTables::of($workflow)
            ->addIndexColumn()
            ->addColumn('tbs_number', function ($query) {
                return $query->resource->number;
            })
            ->addColumn('title', function($query) {
                return $query->resource->title;
            })
            ->addColumn('company', function($query) {
                return $query->resource->company;
            })
            ->addColumn("applied_at", function ($query) {
                return $query->resource->applied_at;
            })
            ->addColumn('action', function ($query) {
                return '<a href="'.route('application.overview',$query->resource->uuid).'" class="btn btn-xs btn-primary">View</a>';
            })
            ->rawColumns(['application','action'])
            ->make(true);
    }


    /*Wf definitions for select*/
    public function getWfDefinitionsByWfModuleForSelect()
    {
//        $definitions = WfDefinition::where('wf_module_id', $wf_module_id)->get()->pluck('level_description', 'id');
//        return $definitions;
        $data["data"] = WfDefinition::where('wf_module_id', request()->input('id'))->get();
        return response()->json($data);
    }


    public function getCertificationWorkflowGroups()
    {
        return view('includes.workflow.workflow_group_checkbox')
            ->with('wf_groups', (new WfModuleGroupRepository())->getCertificationWorkflowGroups());
    }

    public function getImportWorkflowGroups()
    {
        return view('includes.workflow.workflow_group_checkbox')
            ->with('wf_groups', (new WfModuleGroupRepository())->getImportWorkflowGroups());
    }


    public function permitApproval()
    {
        $wf_module_groups = new WfModuleGroupRepository();
        $wf_modules = new WfModuleRepository();
        return view("system/workflow/permit_approval")
            ->with("wf_modules", $wf_modules->getAllActive()->pluck('name', 'id')->all())
            ->with("group_counts", $wf_modules->getPermitApprovalUser())
            ->with("state", "permit_approval")
            ->with("statuses", ['2' => 'All', '1' => 'Assigned to Me', '0' => 'Not Assigned', '3' => 'Assigned to User'])
            ->with("unregistered_modules", $wf_modules->unregisteredMemberNotificationIds())
            ->with("users", $this->users->query()->where("id", "<>", access()->id())->get()->pluck('name', 'id'));
    }

    public function getPermitApproval()
    {
        $datatables = $this->wf_tracks->getForWorkflowDatatable();
        return $datatables->make(true);
    }


    public function setting()
    {
        return view('system.workflow.setting.index')
            ->with('wf_modules', $this->wf_modules->getAllActive());
    }

    public function recall(WfTrack $wfTrack)
    {
        $this->wf_tracks->recall($wfTrack);
        alert()->success(__('Action has been done successfully. now application is on your level. please processed'), __('Workflow Recall'));
        return redirect()->back();
    }


    public function resumeFromWfDone(WfTrack $wfTrack)
    {
        $this->wf_tracks->resumeFromWfDone($wfTrack);
        alert()->success(__('Action has been done successfully. now application is on your level. please processed'), __('Workflow Recall'));
        return redirect()->back();
    }

    public function getSidebarContent()
    {
        // Get the dynamic counts for the sidebar links
        $newQueryCount = $this->wf_tracks->getNewQueryCount();
        $respondedQueryCount = $this->wf_tracks->RespondedQueryCount();
        $myAttendedCount = $this->wf_tracks->MyAttendedCount();

        // Generate the HTML content for the sidebar links with the dynamic counts
        $sidebarContent = '
            <li><a class="side-menu__item" href="' . route('workflow.new') . '"><i class="side-menu__icon ion-archive"></i><span class="side-menu__label">Incoming Requests</span> ' . $newQueryCount . '</a></li>
            <li><a class="side-menu__item" href="' . route('workflow.responded') . '"><i class="side-menu__icon ion-reply"></i><span class="side-menu__label">Responded Requests</span> ' . $respondedQueryCount . '</a></li>
            <li><a class="side-menu__item" href="' . route('workflow.attended') . '"><i class="side-menu__icon ion-reply-all"></i><span class="side-menu__label">Actioned Request</span> ' . $myAttendedCount . '</a></li>
        ';

        // Return the HTML content
        return response()->make($sidebarContent);
    }
}
