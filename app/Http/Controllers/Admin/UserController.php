<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admin\Role;
use App\Models\Admin\AdminUser as User;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Events\AdminActionEvent;
use Auth;

class UserController extends Controller
{
    protected $fields = [
        'name' => '',
        'email' => '',
        'roles' => [],
    ];

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = array();
            $data['draw'] = $request->input('draw');
            $start = $request->input('start');
            $length = $request->input('length');
            $order = $request->input('order');
            $columns = $request->input('columns');
            $search = $request->input('search');
            $data['recordsTotal'] = User::count();
            if (strlen($search['value']) > 0) {
                $data['recordsFiltered'] = User::where(function ($query) use ($search) {
                    $query->where('name', 'LIKE', '%' . $search['value'] . '%')
                        ->orWhere('email', 'like', '%' . $search['value'] . '%');
                })->count();
                $data['data'] = User::where(function ($query) use ($search) {
                    $query->where('name', 'LIKE', '%' . $search['value'] . '%')
                        ->orWhere('email', 'like', '%' . $search['value'] . '%');
                })
                    ->skip($start)->take($length)
                    ->orderBy($columns[$order[0]['column']]['data'], $order[0]['dir'])
                    ->get();
            } else {
                $data['recordsFiltered'] = User::count();
                $data['data'] = User::
                skip($start)->take($length)
                    ->orderBy($columns[$order[0]['column']]['data'], $order[0]['dir'])
                    ->get();
            }
            return response()->json($data);
        }
        return view('admin.user.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $data = [];
        foreach ($this->fields as $field => $default) {
            $data[$field] = old($field, $default);
        }
        $data['rolesAll'] = Role::all()->toArray();
        return view('admin.user.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Requests\AdminUserCreateRequest $request)
    {
        $user = new User();
        foreach ($this->fields as $field => $default) {
            $user->$field = $request->input($field);
        }
        $user->password = bcrypt($request->input('password'));
        unset($user->roles);

        if($user->save()){
            if (is_array($request->input('roles'))) {
                $user->giveRoleTo($request->input('roles'));
            }
            event(new AdminActionEvent('添加了用户' . $user->name));
            return redirect('/admin/user/index')->withSuccess('添加成功！');
        }else{
            return redirect()->back()->withInput()->withErrors('添加失败！');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if($id == 1 && Auth::guard('admin')->user()->id !=1){//id=1的超级管理员只能自己修改
            return response()->view('admin.errors.403', ['previousUrl'=>\URL::previous()]);
        }

        $user = User::findOrFail($id);

        $roles = [];
        if ($user->roles) {
            foreach ($user->roles as $v) {
                $roles[] = $v->id;
            }
        }
        $user->roles = $roles;
        foreach ($this->fields as $field => $default) {
            $data[$field] = old($field, $user->$field);
        }
        $data['rolesAll'] = Role::all()->toArray();
        $data['id'] = (int)$id;
        return view('admin.user.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Requests\AdminUserUpdateRequest $request, $id)
    {
        if($id == 1 && Auth::guard('admin')->user()->id !=1){//id=1的超级管理员只能自己修改
            return response()->view('admin.errors.403', ['previousUrl'=>\URL::previous()]);
        }

        $user = User::findOrFail($id);
        foreach ($this->fields as $field => $default) {
            $user->$field = $request->input($field);
        }
        unset($user->roles);

        if($user->save()){
            $user->giveRoleTo($request->input('roles', []));
            event(new AdminActionEvent('修改了用户' . $user->name));
            return redirect('/admin/user/index')->withSuccess('修改成功！');
        }else{
            return redirect()->back()->withInput()->withErrors('修改失败！');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        foreach ($user->roles as $v) {
            $user->roles()->detach($v);
        }

        if ($user && $user->id != 1 && $user->delete()) {//id=1的超级管理员不能删除
            return redirect()->back()->withSuccess("删除成功！");
        } else {
            return redirect()->back()->withErrors("删除失败！");
        }

    }
}
