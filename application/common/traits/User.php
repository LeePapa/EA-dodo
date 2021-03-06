<?php
/**
 * Created by PhpStorm.
 * User: wuchuguang
 * Date: 17-6-7
 * Time: 下午2:22
 */

namespace app\common\traits;


use app\common\cache\Cache;
use app\common\model\Department;
use app\common\model\DepartmentUserMap;
use app\common\service\Common;
use app\index\service\DepartmentUserMapService;
use app\index\service\Role;
use app\order\service\PackageService;
use think\Exception;

trait User
{
    private $userServer = null;

    private function getUser($userId)
    {
        if (!$this->userServer) {
            $this->userServer = new \app\index\service\User();
        }
        return $this->userServer->getUser($userId);
    }

    /**
     * 获取下属人员信息
     * @param $user_id
     * @return array
     * @throws \think\Exception
     */
    public function getUnderlingInfo($user_id)
    {
        $underling = [];
        try {
            $departments = Cache::store('department')->tree();
            $departmentUserMapService = new DepartmentUserMapService();
            $departments_ids = $departmentUserMapService->getDepartmentByUserId($user_id);
            foreach ($departments_ids as $key => $department_id) {
                if (isset($departments[$department_id])) {
                    if (in_array($user_id, $departments[$department_id]['leader_id'])) {
                        $child = $departments[$department_id]['child_ids'];
                        if (!empty($child)) {
                            $departmentIds = $child;
                            foreach($child as $c => $children){
                                if(isset($departments[$department_id])){
                                    $child_child = $departments[$children]['child_ids'];
                                    if(!empty($child_child)){
                                        $departmentIds = array_merge($departmentIds, $child_child);
                                    }
                                }
                            }
                            array_push($departmentIds, $department_id);
                        } else {
                            $departmentIds = [$department_id];
                        }
                        $userMapModel = new DepartmentUserMap();
                        $userList = $userMapModel->field('user_id')->where('department_id', 'in', $departmentIds)->select();
                        if (!empty($userList)) {
                            foreach ($userList as $k => $user) {
                                array_push($underling, $user['user_id']);
                            }
                        }
                    }
                }
            }
            array_push($underling, $user_id);
            $underling = array_unique($underling);
        } catch (Exception $e) {
        }
        return $underling;
    }

    /**
     * 是否为超级管理员
     * @param int $user_id
     * @return bool
     */
    public function isAdmin($user_id = 0)
    {
        if (empty($user_id)) {
            $userInfo = Common::getUserInfo();
            $user_id = $userInfo['user_id'] ?? 0;
        }
        if (!(new Role())->isAdmin($user_id)) {
            return false;
        }
        return true;
    }

    /**
     * 是否过来仓库
     * @return array
     */
    public function isFilterWarehouse()
    {
        $userInfo = Common::getUserInfo();
        $user_id = $userInfo['user_id'] ?? 0;
        $departmentUserMapService = new DepartmentUserMapService();
        $departments_ids = $departmentUserMapService->getDepartmentByUserId($user_id);
        if (count($departments_ids) == 1) {
            if (!in_array($departments_ids[0], [142, 143])) {
                $parent_id = (new Department())->where(['id' => $departments_ids[0]])->value('pid');
                $departments_ids[0] = $parent_id;
            }
            if ($departments_ids[0] == 142) {
                return 2;
            } else if ($departments_ids[0] == 143) {
                return 6;
            }
        }
        return 0;
    }

    /**
     * 找出该部门下面的所有人员；
     * @param $department_id 部门ID；
     * @param $extra 排除部门ID；
     * @return array
     */
    public function getUserByDepartmentId($department_id, $extra = [])
    {
        $uids = [];
        $uids = DepartmentUserMap::where(['department_id' => $department_id])->column('user_id');
        $departments = Department::where(['pid' => $department_id])->select();

        array_push($extra, $department_id);

        foreach ($departments as $d) {
            //排除；
            if (in_array($d['id'], $extra)) {
                continue;
            }
            $tmpUids = $this->getUserByDepartmentId($d['id'], $extra);
            $uids = array_merge($uids, $tmpUids);
        }
        return $uids;
    }
}