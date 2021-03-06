<?php

namespace App\Http\Controllers;

use Request;
use App\College;
use App\Http\Requests\CollegeRequest;
use Session;

class CollegesController extends Controller
{
    public function index(){
        $colleges = College::paginate(10);
        return view('admin_pages.colleges.index')->with('colleges', $colleges);
    }
    public function create(){
        $college = new College();
        return view('admin_pages.colleges.create')->with('college', $college);
    }
    public function store(CollegeRequest $request){
        $inputs = $request->all();
        College::create($inputs);
        Session::flash('success', 'College was added!');
        return redirect()->route('admin::colleges.create');
    }
    public function edit($college_id){
        $college = College::find($college_id);
        return view('admin_pages.colleges.edit')->with('college', $college);
    }
    public function update(CollegeRequest $request, $college_id){
        $inputs = $request->all();
        $college = College::find($college_id);        
        $college->update($inputs);
        Session::flash('success', 'College was Updated!');
        return redirect()->route('admin::colleges.index');
    }
    public function destroy($college_id){
        $college = College::find($college_id);
        if($college->users->count() > 0){
            Session::flash('success', "The college has registered users and can't be deleted!");
        }
        else{
            College::destroy($college_id);
            Session::flash('success', "College was deleted!");            
        }
        return redirect()->back();         
    }
}
