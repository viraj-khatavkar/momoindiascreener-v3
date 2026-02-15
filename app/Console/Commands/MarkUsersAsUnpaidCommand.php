<?php

namespace App\Console\Commands;

use App\Enums\PlanEnum;
use App\Mail\SubscriptionExpired;
use App\Models\Order;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class MarkUsersAsUnpaidCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:mark-users-as-unpaid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Marks users as unpaid whose plan ends today';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $date = now()->timezone('Asia/Kolkata')->format('Y-m-d');

        $users = User::where('plan_ends_at', $date)->get();

        foreach ($users as $user) {
            $user->is_paid = false;
            $user->save();

            $doesNotHaveNewsletterOrder = Order::query()
                ->where('user_id', $user->id)
                ->where('status', 'paid')
                ->where('plan', PlanEnum::Newsletter)
                ->doesntExist();

            if ($doesNotHaveNewsletterOrder) {
                $user->is_newsletter_paid = false;
                $user->save();
            }

            Mail::to($user)->send(new SubscriptionExpired($user));
        }
    }
}
