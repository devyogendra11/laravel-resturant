<?php

namespace App\Http\Controllers;

use App\Mail\Websitemail;
use App\Models\Client;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class ClientController extends Controller
{
    /**
     * @return Factory|View|Application
     */
    public function ClientLogin(): Factory|View|Application
    {
        return view('client.client_login');
    }

    /**
     * @return Factory|View|Application
     */
    public function ClientRegister(): Factory|View|Application
    {
        return view('client.client_register');
    }

    /**
     * @return Factory|View|Application
     */
    public function ClientForgotPassword(): Application|View|Factory
    {
        return view('client.forgot_password');
    }

    /**
     * @return Factory|View|Application
     */
    public function ClientDashboard(): Factory|View|Application
    {
        return view('client.index');
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function ClientRegisterSubmit(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'unique:clients'],
            'name'  => ['required', 'string', 'max:200'],
            'password' => ['required']
        ]);

        Client::insert([
            'name' => $request->name,
            'phone' => $request->phone,
            'address' => $request->address,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'client',
            'status' => 0
        ]);

        $notification = array(
            'message' => 'Client register successfully!',
            'alert-type' => 'success'
        );

        return redirect()->route('client.login')->with($notification);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function ClientLoginSubmit(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => 'required'
        ]);

        $check = $request->all();
        $data = [
            'email' => $check['email'],
            'password' => $check['password'],
        ];

        if (Auth::guard('client')->attempt($data)){
            $notification = array(
                'message' => 'Login Successfully!',
                'alert-type' => 'success'
            );
            return redirect()->route('client.dashboard')->with($notification);
        } else {
            $notification = array(
                'message' => 'Invalid Credentials!',
                'alert-type' => 'error'
            );
            return redirect()->route('client.login')->with($notification);
        }
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function ClientForgotSubmitEmail(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email'
        ]);
        $clientData = Client::where('email', $request->email)->first();
        if (!$clientData) {
            $notification = array(
                'message' => 'Email not found!',
                'alert-type' => 'error'
            );
            return redirect()->back()->with($notification);
        }
        $token = hash('sha256', time());
        $clientData->token = $token;
        $clientData->update();

        $resetLink = url('client/reset-password/'.$token.'/'.$request->email);
        $subject = 'Reset Password';
        $message = "Please use below link to reset your password<br>";
        $message .= "<a href='".$resetLink."'> Click here </a>";

        Mail::to($request->email)->send(new Websitemail($subject, $message));

        $notification = array(
            'message' => 'Reset password link send on your email!!',
            'alert-type' => 'success'
        );
        return redirect()->back()->with($notification);
    }

    /**
     * @param string $token
     * @param string $email
     * @return Factory|Application|View|RedirectResponse
     */
    public function ClientResetPassword(string $token, string $email): Factory|Application|View|RedirectResponse
    {
        $clientData = Client::where('email', $email)->where('token', $token)->first();
        if(!$clientData) {
            $notification = array(
                'message' => 'Invalid token or email!',
                'alert-type' => 'error'
            );
            return redirect()->route('client.login')->with($notification);
        }
        return view('client.reset_password', compact('token', 'email'));
    }

    public function ClientResetPasswordSubmit(Request $request)
    {
        $request->validate([
            'password' => 'required',
            'confirm_password' => 'required|same:password'
        ]);

        $clientData = Client::where('email', $request->email)->where('token', $request->token)->first();

        if ($clientData) {
            $clientData->password = Hash::make($request->password);
            $clientData->update();

            $notification = array(
                'message' => 'Password updated successfully!',
                'alert-type' => 'success'
            );

            return redirect()->route('client.login')->with($notification);
        }
        $notification = array(
            'message' => 'Some error occur while updating!',
            'alert-type' => 'error'
        );

        return redirect()->route('client.login')->with($notification);
    }

    /**
     * @return RedirectResponse
     */
    public function ClientLogout(): RedirectResponse
    {
        Auth::guard('client')->logout();
        $notification = array(
            'message' => 'Logout Successfully!',
            'alert-type' => 'success'
        );
        return redirect()->route('client.login')->with($notification);
    }

    /**
     * @return Factory|View|Application
     */
    public function ClientProfile(): Application|View|Factory
    {
        $id = Auth::guard('client')->id();
        $profileData = Client::find($id);

        return view('client.client_profile', compact('profileData'));
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function ClientProfileStore(Request $request): RedirectResponse
    {
        $id = Auth::guard('client')->id();
        $clientData = Client::find($id);

        $request->validate([
            'email' => 'email',
        ]);

        if ($request->email) {
            $clientData->email = $request->email;
        }
        $clientData->name = $request->name;
        $clientData->phone = $request->phone;
        $clientData->address = $request->address;
        $oldPhoto = $clientData->photo;

        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $filename = time().'.'.$file->getClientOriginalExtension();
            $file->move(public_path('upload/client_images'), $filename);
            $clientData->photo = $filename;

            if ($oldPhoto && $oldPhoto !== $filename) {
                $this->deleteOldImage($oldPhoto);
            }
        }
        $clientData->save();

        $notification = array(
            'message' => 'Profile Updated Successfully!',
            'alert-type' => 'success'
        );

        return redirect()->back()->with($notification);

    }

    /**
     * @param $oldPhoto
     * @return void
     */
    private function deleteOldImage($oldPhoto): void
    {
        $fullPath = public_path('upload/client_images/'.$oldPhoto);
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }
}
