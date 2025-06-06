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

namespace App\Multitenancy\Tasks;

use App\Models\Tenant;
use Exception;
use Illuminate\Support\Facades\Storage;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;

class SwitchS3FilesystemTask implements SwitchTenantTask
{
    public function __construct(
        protected ?string $originalKey = null,
        protected ?string $originalSecret = null,
        protected ?string $originalRegion = null,
        protected ?string $originalBucket = null,
        protected ?string $originalUrl = null,
        protected ?string $originalEndpoint = null,
        protected bool $originalUsePathStyleEndpoint = false,
        protected bool $originalThrow = false,
        protected ?string $originalRoot = null,
    ) {
        $this->originalKey ??= config('filesystems.disks.s3.key');
        $this->originalSecret ??= config('filesystems.disks.s3.secret');
        $this->originalRegion ??= config('filesystems.disks.s3.region');
        $this->originalBucket ??= config('filesystems.disks.s3.bucket');
        $this->originalUrl ??= config('filesystems.disks.s3.url');
        $this->originalEndpoint ??= config('filesystems.disks.s3.endpoint');
        $this->originalUsePathStyleEndpoint = ! is_null(config('filesystems.disks.s3.use_path_style_endpoint'))
            ? config('filesystems.disks.s3.use_path_style_endpoint')
            : false;
        $this->originalThrow = ! is_null(config('filesystems.disks.s3.throw'))
            ? config('filesystems.disks.s3.throw')
            : false;
        $this->originalRoot ??= config('filesystems.disks.s3.root');
    }

    public function makeCurrent(IsTenant $tenant): void
    {
        throw_if(
            ! $tenant instanceof Tenant,
            new Exception('Tenant is not an instance of Tenant')
        );

        $config = $tenant->config;

        $this->setFilesystemConfig(
            key: $config->s3Filesystem->key,
            secret: $config->s3Filesystem->secret,
            region: $config->s3Filesystem->region,
            bucket: $config->s3Filesystem->bucket,
            url: $config->s3Filesystem->url,
            endpoint: $config->s3Filesystem->endpoint,
            usePathStyleEndpoint: $config->s3Filesystem->usePathStyleEndpoint,
            throw: $config->s3Filesystem->throw,
            root: $config->s3Filesystem->root,
        );
    }

    public function forgetCurrent(): void
    {
        $this->setFilesystemConfig(
            key: $this->originalKey,
            secret: $this->originalSecret,
            region: $this->originalRegion,
            bucket: $this->originalBucket,
            url: $this->originalUrl,
            endpoint: $this->originalEndpoint,
            usePathStyleEndpoint: $this->originalUsePathStyleEndpoint,
            throw: $this->originalThrow,
            root: $this->originalRoot,
        );
    }

    protected function setFilesystemConfig(
        ?string $key,
        ?string $secret,
        ?string $region,
        ?string $bucket,
        ?string $url,
        ?string $endpoint,
        bool $usePathStyleEndpoint,
        bool $throw,
        ?string $root,
    ): void {
        config([
            'filesystems.disks.s3.key' => $key,
            'filesystems.disks.s3.secret' => $secret,
            'filesystems.disks.s3.region' => $region,
            'filesystems.disks.s3.bucket' => $bucket,
            'filesystems.disks.s3.url' => $url,
            'filesystems.disks.s3.endpoint' => $endpoint,
            'filesystems.disks.s3.use_path_style_endpoint' => $usePathStyleEndpoint,
            'filesystems.disks.s3.throw' => $throw,
            'filesystems.disks.s3.root' => $root,
        ]);

        Storage::forgetDisk('s3');
    }
}
