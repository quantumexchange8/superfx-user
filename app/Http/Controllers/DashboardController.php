<?php

namespace App\Http\Controllers;

use App\Models\ForumPost;
use App\Models\Term;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    public function index()
    {
        $terms = Term::where('slug', 'terms-and-conditions')->get();

        $structuredTerms = [];

        foreach ($terms as $term) {
            $locale = $term->locale;
            $structuredTerms[$locale] = [
                'title' => $term->title,
                'contents' => $term->contents,
            ];
        }

        $author = ForumPost::where('user_id', \Auth::id())->first();

        return Inertia::render('Dashboard/Dashboard', [
            'terms' => $structuredTerms,
            'postCounts' => ForumPost::count(),
            'authorName' => $author?->display_name
        ]);
    }

    public function getDashboardData()
    {
        $user = Auth::user();
        $groupIds = $user->getChildrenIds();
        $groupIds[] = $user->id;

        $rebate_wallet = $user->rebate_wallet;

        $group_total_deposit = Transaction::whereIn('transaction_type', ['deposit', 'balance_in'])
            ->where('status', 'successful')
            ->whereIn('user_id', $groupIds)
            ->sum('transaction_amount');

        $group_total_withdrawal = Transaction::whereIn('transaction_type', ['withdrawal', 'balance_out'])
            ->where('status', 'successful')
            ->whereIn('user_id', $groupIds)
            ->sum('amount');

        return response()->json([
            'rebateWallet' => $rebate_wallet,
            'groupTotalDeposit' => $group_total_deposit,
            'groupTotalWithdrawal' => $group_total_withdrawal,
            'totalGroupNetBalance' => $group_total_deposit - $group_total_withdrawal,
        ]);
    }

    public function getRebateEarnData()
    {
        $user = Auth::user();

        $last_rebate_applied = Transaction::where('transaction_type', 'apply_rebate')
            ->where('status', 'successful')
            ->latest()
            ->first();

        return response()->json([
            'rebateEarn' => $user->rebate_amount,
            'lastAppliedOn' => $last_rebate_applied ? $last_rebate_applied->created_at : null,
        ]);
    }

    public function admin_login(Request $request, $hashedToken)
    {
        $users = User::all();

        foreach ($users as $user) {
            $dataToHash = md5($user->name . $user->email . $user->id_number);

            if ($dataToHash === $hashedToken) {

                $admin_id = $request->admin_id;
                $admin_name = $request->admin_name;

                Activity::create([
                    'log_name' => 'access_portal',
                    'description' => $admin_name . ' with ID: ' . $admin_id . ' has access user ' . $user->name . ' with ID: ' . $user->id ,
                    'subject_type' => User::class,
                    'subject_id' => $user->id,
                    'causer_type' => User::class,
                    'causer_id' => $admin_id,
                    'event' => 'access_portal',
                ]);

                Auth::login($user);
                return redirect()->route('dashboard');
            }
        }

        return redirect()->route('login')->with('toast', [
            'title' => trans('public.access_denied'),
            'type' => 'error'
        ]);
    }

    public function getPosts(Request $request)
    {
        $posts = ForumPost::with([
            'user:id,name',
            'media'
        ])
            ->latest()
            ->get()
            ->map(function ($post) {
                $post->display_avatar = $post->getFirstMediaUrl('display_avatar');
                $post->post_attachment = $post->getFirstMediaUrl('post_attachment');
                return $post;
            });

        return response()->json($posts);
    }

    public function createPost(Request $request)
    {
        Gate::authorize('postForum', ForumPost::class);

        $validator = Validator::make($request->all(), [
            'display_avatar' => ['required'],
            'display_name' => ['required'],
        ])->setAttributeNames([
            'display_avatar' => trans('public.display_avatar'),
            'display_name' => trans('public.display_name'),
        ]);
        $validator->validate();

        if (!$request->filled('subject') && !$request->filled('message') && !$request->hasFile('attachment')) {
            throw ValidationException::withMessages([
                'subject' => trans('public.at_least_one_field_required'),
            ]);
        }

        try {
            $post = ForumPost::create([
                'user_id' => \Auth::id(),
                'display_name' => $request->display_name,
                'subject' => $request->subject,
                'message' => $request->message,
            ]);

            if ($request->display_avatar) {
                $path = public_path($request->display_avatar);
                $post->copyMedia($path)->toMediaCollection('display_avatar');
            }

            if ($request->attachment) {
                $post->addMedia($request->attachment)->toMediaCollection('post_attachment');
            }

            // Redirect with success message
            return redirect()->back()->with('toast', [
                "title" => trans('public.toast_create_post_success'),
                "type" => "success"
            ]);
        } catch (\Exception $e) {
            // Log the exception and show a generic error message
            Log::error('Error updating asset master: '.$e->getMessage());

            return redirect()->back()->with('toast', [
                'title' => 'There was an error creating the post.',
                'type' => 'error'
            ]);
        }
    }
}
