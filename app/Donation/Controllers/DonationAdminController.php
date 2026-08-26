<?php

declare(strict_types=1);

namespace App\Donation\Controllers;

use App\Common\Enums\ErrorCode;
use App\Donation\Business\DonationBusiness;
use plugin\saiadmin\basic\BaseController;
use plugin\saiadmin\service\Permission;
use support\Request;
use support\Response;
use Webman\Http\UploadFile;

final class DonationAdminController extends BaseController
{
    #[Permission('捐赠记录列表', 'donation:index')]
    public function index(Request $request): Response
    {
        return $this->success((new DonationBusiness())->page($request->only(['keyword', 'status']), max(1, (int) $request->get('page', 1)), min(100, max(1, (int) $request->get('pageSize', 20)))));
    }

    #[Permission('新增捐赠记录', 'donation:create')]
    public function save(Request $request): Response
    {
        return $this->success((new DonationBusiness())->save($request->post()));
    }

    #[Permission('编辑捐赠记录', 'donation:update')]
    public function update(Request $request): Response
    {
        return $this->success((new DonationBusiness())->update((int) $request->post('id'), $request->post()));
    }

    #[Permission('删除捐赠记录', 'donation:delete')]
    public function destroy(Request $request): Response
    {
        (new DonationBusiness())->destroy((array) $request->post('ids', []));

        return $this->success('删除成功');
    }

    #[Permission('收款码配置', 'donation:channel')]
    public function channels(Request $request): Response
    {
        return $this->success((new DonationBusiness())->channels());
    }

    #[Permission('上传收款码', 'donation:channel:update')]
    public function channelUpdate(Request $request): Response
    {
        $file = current($request->file());
        if (!$file instanceof UploadFile) {
            ErrorCode::DONATION_CHANNEL_INVALID->throw();
        }

        return $this->success((new DonationBusiness())->updateChannel((string) $request->post('method'), $file, (string) $request->post('name'), filter_var($request->post('status', true), FILTER_VALIDATE_BOOL), (int) $request->post('sort', 0)));
    }

    #[Permission('捐赠统计', 'donation:stats')]
    public function stats(Request $request): Response
    {
        return $this->success((new DonationBusiness())->stats());
    }
}
