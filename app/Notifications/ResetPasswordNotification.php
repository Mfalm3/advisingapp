<?php

/*
<COPYRIGHT>

    Copyright © 2016-2025, Canyon GBS LLC. All rights reserved.

    Advising App™ is licensed under the Elastic License 2.0. For more details,
    see https://github.com/canyongbs/advisingapp/blob/main/LICENSE.

    Notice:

    - You may not provide the software to third parties as a hosted or managed
      service, where the service provides users with access to any substantial set of
      the features or functionality of the software.
    - You may not move, change, disable, or circumvent the license key functionality
      in the software, and you may not remove or obscure any functionality in the
      software that is protected by the license key.
    - You may not alter, remove, or obscure any licensing, copyright, or other notices
      of the licensor in the software. Any use of the licensor’s trademarks is subject
      to applicable law.
    - Canyon GBS LLC respects the intellectual property rights of others and expects the
      same in return. Canyon GBS™ and Advising App™ are registered trademarks of
      Canyon GBS LLC, and we are committed to enforcing and protecting our trademarks
      vigorously.
    - The software solution, including services, infrastructure, and code, is offered as a
      Software as a Service (SaaS) by Canyon GBS LLC.
    - Use of this software implies agreement to the license terms and conditions as stated
      in the Elastic License 2.0.

    For more information or inquiries please visit our website at
    https://www.canyongbs.com or contact us via email at legal@canyongbs.com.

</COPYRIGHT>
*/

namespace App\Notifications;

use AdvisingApp\Notification\Notifications\Attributes\SystemNotification;
use AdvisingApp\Notification\Notifications\Messages\MailMessage;
use App\Models\NotificationSetting;
use App\Models\User;
use Filament\Notifications\Auth\ResetPassword;

#[SystemNotification]
class ResetPasswordNotification extends ResetPassword
{
    /**
     * @inheritDoc
     */
    public function toMail($notifiable): MailMessage
    {
        return MailMessage::make()
            ->settings($this->resolveNotificationSetting($notifiable))
            ->subject(__('Reset Password Notification'))
            ->line(__('You are receiving this email because we received a password reset request for your account.'))
            ->action(__('Reset Password'), $this->resetUrl($notifiable))
            ->line(__('This password reset link will expire in :count minutes.', ['count' => config('auth.passwords.' . config('auth.defaults.passwords') . '.expire')]))
            ->line(__('If you did not request a password reset, no further action is required.'));
    }

    private function resolveNotificationSetting(User $notifiable): ?NotificationSetting
    {
        return $notifiable->team?->division?->notificationSetting?->setting;
    }
}
