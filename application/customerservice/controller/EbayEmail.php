<?php
// +----------------------------------------------------------------------
// | 客服邮件功能控制器
// +----------------------------------------------------------------------
// | File  : AmazonEmail.php
// +----------------------------------------------------------------------
// | Author: LiuLianSen <3024046831@qq.com>
// +----------------------------------------------------------------------
// | Date  : 2017-07-18
// +----------------------------------------------------------------------
namespace app\customerservice\controller;


use app\common\controller\Base;
use app\common\model\customerservice\EmailList;
use app\order\service\OrderService;
use imap\EmailAccount;
use think\Db;
use think\db\Query;
use think\Log;
use think\Request;
use app\customerservice\service\EbayEmail as EbayEmailServ;
use app\api\controller\Post;
use Exception;

/**
 * @module ebay和paypal客服管理
 * @title ebay和paypal售后邮件
 * @url /ebay-emails
 */
class EbayEmail extends Base
{

    const AMAZON_CHANNEL_ID = 2;
    const TEST_USER_ID = 1;


    protected  $result  = ['code' => 200,'message' =>''];


    /**
     * @var EbayEmailServ
     */
    protected $defServ = null;


    /**
     * @param $msg
     * @param $code
     * @param array $append
     */
    private function setResult( $msg, $code = 200, $append = [])
    {
        $this->result['code'] = $code;
        $this->result['message'] = $msg;
        if($append){
            $this->result= array_merge($this->result,$append);
        }
    }

    public function init()
    {
        parent::init(); // TODO: Change the autogenerated stubar_dump(1);
        $this->defServ = new EbayEmailServ();
    }


    /**
     * @title 收件箱
     * @method get
     * @param Request $request
     * @return \think\response\Json
     */
    public function index(Request $request)
    {
        try {
            return json($this->defServ->inbox($request->param()));
        } catch (Exception $e) {
            return json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * @title 侵权邮件收件箱
     * @url infringement-box
     * @method get
     * @param Request $request
     * @return \think\response\Json
     */
    public function infringementBox(Request $request)
    {
        try {
            return json($this->defServ->infringementBox($request->param()));
        } catch (Exception $e) {
            return json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * @title 发件箱
     * @url outbox
     * @method get
     * @param Request $request
     * @return \think\response\Json
     */
    public function outbox(Request $request)
    {
        try {
            return json($this->defServ->outbox($request->param()));
        } catch (Exception $e) {
            return json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * @title 转到收件箱
     * @url turn-inbox
     * @method put
     * @param Request $request
     * @return \think\response\Json
     */
    public function turnToInbox(Request $request)
    {
        try {
            $params = $request->param();
            if(!param($params, 'ids') ){
                return json(['message'=>'参数错误'],400);
            }
            $result = $this->defServ->turnToInbox($params['ids']);
            if($result){
                return json(['status'=>1,'message'=>'已转到收件箱'],200);
            }else{
                return json(['status'=>0,'message'=>'转到收件箱失败'],400);
            }
        } catch (Exception $e) {
            return json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * @title 垃圾箱
     * @url trashbox
     * @method get
     * @param Request $request
     * @return \think\response\Json
     */
    public function trashBox(Request $request)
    {
        try {
            return json($this->defServ->trashbox($request->param()));
        } catch (Exception $e) {
            return json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * @title 发邮件
     * @url send
     * @method post
     * @param Request $request
     * @return \think\response\Json
     */
    public function send(Request $request)
    {
        try {
            $this->defServ->send($request->param());
            return json(['message' => '发送成功']);
        } catch (Exception $e) {
            return json(['message' => $e->getMessage()], 400);
        }
    }


    /**
     * @title 收取指定平台账号的邮件
     * @url email-account/receive/:account_id
     * @method get
     * @param Request $request
     * @return \think\response\Json
     */
    public function receiveEmails(Request $request)
    {
        try {
            set_time_limit(0);
            $params=$request->param();

            $platform= '';
            if (isset($params['platform']) && in_array($params['platform'], ['ebay', 'paypal'])) {
                $platform = $params['platform'];
            }

            $accountId = $request->param('account_id/d', null);
            if (empty($accountId)) {
                throw new Exception('平台账号id未设置', 400);
            }
            $accRecord = Db::table('email_accounts')->where('account_id',$accountId)->find();
            if($accRecord){
                $syncQty = $this->defServ->receiveEmail(
                        new EmailAccount(  $accRecord['account_id'],
                        $accRecord['email_account'],
                        $accRecord['email_password'],
                        $accRecord['imap_url'],
                        $accRecord['imap_ssl_port'],
                        $accRecord['smtp_url'],
                        $accRecord['smtp_ssl_port'],
                        $platform), $accRecord['id']);
                return json(['code' => 200, 'message' => '','received_qty'=>$syncQty]);
            }else{
                throw new Exception('平台账号没有设置邮件账号',404);
            }
        } catch (Exception $ex) {
            throw new Exception($ex->getMessage());exit;
            $code = $ex->getCode();
            $message = '程序内部错误';
            if ($code != 0) $message = $ex->getMessage();
            return json(['code' => $code, 'message' => $message], $code);
        }
    }

    /**
     * @title 标记已读
     * @method put
     * @url read
     * @param Request $request
     * @return \think\response\Json
     */
    public function markRead(Request $request)
    {
        try {
            $params = $request->param();
            if(!param($params, 'ids') ){
                return json(['message'=>'参数错误'],400);
            }
            $result = $this->defServ->markRead($params['ids']);
            if($result){
                return json(['status'=>1,'message'=>'标记已读成功'],200);
            }else{
                return json(['status'=>0,'message'=>'标记已读失败'],400);
            }
        } catch (Exception $ex) {
            return json($ex->getMessage(),400);
        }
    }

    /**
     * @title 标记未读
     * @method put
     * @url unread
     * @param Request $request
     * @return \think\response\Json
     */
    public function markUnRead(Request $request)
    {
        try {
            $params = $request->param();
            if(!param($params, 'ids') ){
                return json(['message'=>'参数错误'],400);
            }
            $result = $this->defServ->markUnRead($params['ids']);
            if($result){
                return json(['status'=>1,'message'=>'标记未读成功'],200);
            }else{
                return json(['status'=>0,'message'=>'标记未读失败'],400);
            }
        } catch (Exception $ex) {
            return json($ex->getMessage(),400);
        }
    }

    /**
     * @title 标记未读
     * @method put
     * @url trash
     * @param Request $request
     * @return \think\response\Json
     */
    public function markTrash(Request $request)
    {
        try {
            $params = $request->param();
            if(!param($params, 'ids') ){
                return json(['message'=>'参数错误'],400);
            }
            $result = $this->defServ->markTrash($params['ids']);
            if($result){
                return json(['status'=>1,'message'=>'已标记为垃圾邮件'],200);
            }else{
                return json(['status'=>0,'message'=>'标记垃圾邮件失败'],400);
            }
        } catch (Exception $ex) {
            return json($ex->getMessage(),400);
        }
    }

    /**
     * @title 获取客服对应的账号
     * @method GET
     * @apiReturn data:账号信息
     * @url account
     * @return \think\Response
     */
    public function getEbayAccountMessageTotal(Request $request)
    {
        try {
            $params = $request->param();
            $datas = $this->defServ->getEbayAccountMessageTotal($params);

            return json($datas);
        } catch (Exception $e) {
            return json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * @title 回复或转发邮件
     * @url reply
     * @method post
     * @param Request $request
     * @return \think\response\Json
     */
    public function replyEmail(Request $request)
    {
        try {
            $this->defServ->replyEmail($request->param());
            return json(['message' => '发送成功']);
        } catch (\Exception $ex) {
            $msg = $ex->getMessage();
            $code = $ex->getCode();
            return json(['message' => $msg], $code);
        }
    }


    /**
     * @title 失败邮件重新发送
     * @url resend
     * @method post
     * @param Request $request
     * @return \think\response\Json
     */
    public function reSendMail(Request $request)
    {
        try {
            $this->defServ->reSendMail($request->param());
            return json(['message' => '发送成功']);
        } catch (Exception $ex) {
            $code = $ex->getCode();
            $message = $ex->getMessage();
            return json(['message' => $message], $code);
        }
    }

    /**
     * @title 收件人邮件列表
     * @url receiver-mailAddr
     * @method get
     * @param Request $request
     * @return \think\response\Json
     */
    public function ReceiverMailsAddr(Request $request)
    {
        try {
            $data = $this->defServ->ReceiverMailsAddr($request->param());
            return json($data);
        } catch (Exception $ex) {
            $code = $ex->getCode();
            $message = $ex->getMessage();
            return json(['message' => $message], $code);
        }
    }

    /**
     * @title 发件人邮件列表
     * @url send-mailAddr
     * @method get
     * @param Request $request
     * @return \think\response\Json
     */
    public function SenderMailsAddr(Request $request)
    {
        try {
            $data = $this->defServ->SenderMailsAddr($request->param());
            return json($data);
        } catch (Exception $ex) {
            $code = $ex->getCode();
            $message = $ex->getMessage();
            return json(['message' => $message], $code);
        }
    }

    /**
     * @title 未读邮件数
     * @url unread
     * @method get
     * @param Request $request
     * @return \think\response\Json
     */
    public function unreadAmount(Request $request)
    {
        try {
            $data = $this->defServ->unreadAmount($request->param());
            return json($data);
        } catch (Exception $ex) {
            $code = $ex->getCode();
            $message = $ex->getMessage();
            return json(['message' => $message], $code);
        }
    }

    /**
     * @title 标记置顶
     * @method put
     * @url top
     * @param Request $request
     * @return \think\response\Json
     */
    public function markTop(Request $request)
    {
        try {
            $params = $request->param();
            if(!param($params, 'ids') ){
                return json(['message'=>'参数错误'],400);
            }
            $result = $this->defServ->markTop($params['ids']);
            if($result){
                return json(['status'=>1,'message'=>'邮件已置顶'],200);
            }else{
                return json(['status'=>0,'message'=>'邮件置顶失败'],400);
            }
        } catch (Exception $ex) {
            return json($ex->getMessage(),400);
        }
    }

    /**
     * @title 取消置顶
     * @method put
     * @url cancel-top
     * @param Request $request
     * @return \think\response\Json
     */
    public function cancelTop(Request $request)
    {
        try {
            $params = $request->param();
            if(!param($params, 'ids') ){
                return json(['message'=>'参数错误'],400);
            }
            $result = $this->defServ->cancelTop($params['ids']);
            if($result){
                return json(['status'=>1,'message'=>'邮件已取消置顶'],200);
            }else{
                return json(['status'=>0,'message'=>'邮件取消置顶失败'],400);
            }
        } catch (Exception $ex) {
            return json($ex->getMessage(),400);
        }
    }

}
