<?php

namespace HamisJuma\Workflow\Http\Controllers;

use HamisJuma\Workflow\Models\WfTrack;
use HamisJuma\Workflow\Repositories\WfTrackUserRepository;
use Illuminate\Http\Request;

class WfTrackUserController extends Controller
{
    protected $wf_track_users;

    public function __construct()
    {
        $this->wf_track_users = (new WfTrackUserRepository());
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function assignment(Request $request, WfTrack $wfTrack)
    {
        $this->wf_track_users->assignment($wfTrack, $request['user_id']);
        alert()->success('User assigned Successfully');
        return redirect()->back();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
