<?php

namespace App\Http\Controllers\User\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\Team;
use App\Models\Role;
use App\Models\EmployeeType;
use DateTime;
use App\Http\Requests\EmployeeEditRequest;


class VendorController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
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
        $objEmployee = Employee::where('delete_flag', 0)->findOrFail($id)->toArray();
        $dataTeam = Team::select('id', 'name')->where('delete_flag', 0)->get()->toArray();
        $dataRoles = Role::select('id', 'name')->where('delete_flag', 0)->get()->toArray();
        $dataEmployeeTypes = EmployeeType::select('id', 'name')->get()->toArray();

        return view('admin.module.employees.edit', ['objEmployee' => $objEmployee, 'dataTeam' => $dataTeam, 'dataRoles' => $dataRoles, 'dataEmployeeTypes' => $dataEmployeeTypes]);
    }

    public function update(EmployeeEditRequest $request, $id)
    {
        $objEmployee = Employee::select('email')->where('email', 'like', $request->email)->where('id', '<>', $id)->get()->toArray();
        $pass = $request->password;
        $employee = Employee::find($id);
        $employee->email = $request->email;
        if ($pass != null) {
            if (strlen($pass) < 6) {
                return back()->with(['minPass' => 'The Password must be at least 6 characters.', 'employee' => $employee]);
            } else {
                $employee->password = bcrypt($request->password);
            }
        }
        $employee->name = $request->name;
        $employee->birthday = $request->birthday;
        $employee->gender = $request->gender;
        $employee->mobile = $request->mobile;
        $employee->address = $request->address;
        $employee->marital_status = $request->marital_status;
        $employee->company = $request->company;
        $employee->startwork_date = $request->startwork_date;
        $employee->endwork_date = $request->endwork_date;
        $employee->employee_type_id = $request->employee_type_id;
        $employee->role_id = $request->role_id;
        $employee->updated_at = new DateTime();
        if ($objEmployee != null) {
            \Session::flash('msg_fail', 'Edit failed!!! Email already exists!!!');
            return back()->with(['vendors' => $employee]);
            // return redirect('vendors/'.$id.'/edit') -> with(['msg_fail' => 'Edit failed!!! Email already exists']);
        } else {
            $employee->save();
            \Session::flash('msg_success', 'Account successfully edited!!!');
            return redirect('vendors');
        }
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