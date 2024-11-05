<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Mail\Websitemail;
use Illuminate\Support\Facades\Mail;

class AdminController extends Controller
{
    /**
     * @return Factory|View|Application
     */
    public function AdminLogin(): Factory|View|Application
    {
        return view('admin.login');
    }

    /**
     * @return Factory|View|Application
     */
    public function AdminDashboard(): Factory|View|Application
    {
        return view('admin.index');
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function AdminLoginSubmit(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $check = $request->all();
        $data = [
            'email' => $check['email'],
            'password' => $check['password'],
        ];

        if (Auth::guard('admin')->attempt($data)) {
            return redirect()->route('admin.dashboard')->with('success', 'Login Successfully');
        } else {
            return redirect()->route('admin.login')->with('error', 'Invalid Credentials!');
        }
    }

    /**
     * @return RedirectResponse
     */
    public function AdminLogout(): RedirectResponse
    {
        Auth::guard('admin')->logout();
        return redirect()->route('admin.login')->with('success', 'Logout Successfully');
    }

    /**
     * @return Factory|View|Application
     */
    public function AdminForgotPassword(): Factory|View|Application
    {
        return view('admin.forgot_password');
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function AdminPasswordSubmit(Request $request): RedirectResponse
    {
        $request->validate([
           'email' => 'required|email'
        ]);
        $admin_data = Admin::where('email', $request->email)->first();
        if (!$admin_data) {
            return redirect()->back()->with('error', 'Email not found!');
        }
        $token = hash('sha256', time());
        $admin_data->token = $token;
        $admin_data->update();

        $resetLink = url('admin/reset-password/'.$token.'/'.$request->email);
        $subject = 'Reset Password';
        $message = "Please use below link to reset your password<br>";
        $message .= "<a href='".$resetLink."'> Click here </a>";

        Mail::to($request->email)->send(new Websitemail($subject, $message));
        return redirect()->back()->with('success', 'Reset password link send on your email!');
    }

    /**
     * @param string $token
     * @param string $email
     * @return Factory|View|Application|RedirectResponse
     */
    public function AdminResetPassword(string $token, string $email): View|Application|Factory|RedirectResponse
    {
        $adminData = Admin::where('email', $email)->where('token', $token)->first();

        if(!$adminData) {
            return redirect()->route('admin.login')->with('error', 'Invalid token or email');
        }
        return view('admin.reset_password', compact('token', 'email'));
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function AdminResetPasswordSubmit(Request $request): RedirectResponse
    {
        $request->validate([
           'password' => 'required',
           'confirm_password' => 'required|same:password'
        ]);

        $adminData = Admin::where('email', $request->email)->where('token', $request->token)->first();

        if ($adminData) {
            $adminData->password = Hash::make($request->password);
            $adminData->token = "";
            $adminData->update();
            return redirect()->route('admin.login')->with('success', 'Password Reset Successfully');
        }

        return redirect()->route('admin.login')->with('error', 'Token expire!');
    }

    /**
     * @return Factory|View|Application
     */
    public function AdminProfile(): Application|View|Factory
    {
        $id = Auth::guard('admin')->id();
        $profileData = Admin::find($id);

        return view('admin.admin_profile', compact('profileData'));
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function AdminProfileStore(Request $request): RedirectResponse
    {
        $id = Auth::guard('admin')->id();
        $adminData = Admin::find($id);

        $request->validate([
            'email' => 'email',
        ]);

        if ($request->email) {
            $adminData->email = $request->email;
        }
        $adminData->name = $request->name;
        $adminData->phone = $request->phone;
        $adminData->address = $request->address;
        $oldPhoto = $adminData->photo;

        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $filename = time().'.'.$file->getClientOriginalExtension();
            $file->move(public_path('upload/admin_images'), $filename);
            $adminData->photo = $filename;

            if ($oldPhoto && $oldPhoto !== $filename) {
                $this->deleteOldImage($oldPhoto);
            }
        }
        $adminData->save();

        $notification = array(
            'message' => 'Profile Updated Successfully!',
            'alert-type' => 'success'
        );

        return redirect()->back()->with($notification);
    }

    /**
     * @param string $oldPhoto
     * @return void
     */
    private function deleteOldImage(string $oldPhoto): void
    {
        $fullPath = public_path('upload/admin_images/'.$oldPhoto);
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    /**
     * @return Factory|View|Application
     */
    public function AdminChangePassword(): Application|View|Factory
    {
        $id = Auth::guard('admin')->id();
        $profileData = Admin::find($id);

        return view('admin.admin_change_password', compact('profileData'));
    }

    public function AdminPasswordUpdate(Request $request)
    {
        $adminData = Auth::guard('admin')->user();

        $request->validate([
            'old_password' => 'required',
            'new_password' => 'required|confirmed',
        ]);

        if(!Hash::check($request->old_password, $adminData->password)) {
            $notification = array(
                'message' => "Old password doesn't match!",
                'alert-type' => 'error'
            );

            return back()->with($notification);
        }

        Admin::whereId($adminData->id)->update([
            'password' => Hash::make($request->new_password)
        ]);

        $notification = array(
            'message' => "Password updated successfully!",
            'alert-type' => 'success'
        );

        return back()->with($notification);
    }
}
