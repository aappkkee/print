<?php

// ===================================================================
// | FileName: 		TokenController.class.php
// ===================================================================
// | Discription：	TokenController 默认控制器
//		<命名规范：>
// ===================================================================
// +------------------------------------------------------------------
// | 云印南开
// +------------------------------------------------------------------
// | Copyright (c) 2014-2015 云印南开团队 All rights reserved.
// +------------------------------------------------------------------
/**
 * Class and Function List:
 * Function list:
 * - index()
 * - token()
 * Classes list:
 * - TokenController extends RestController
 */
namespace API\Controller;
use Think\Controller\RestController;
class TokenController extends RestController
{
	
	protected $allowMethod = array('post', 'put', 'delete',);
	protected $defaultType = 'json';
	
	// REST允许请求的资源类型列表
	protected $allowType   = array('xml', 'json');
	
	/**
	 *index
	 *api令牌生成
	 * 支持操作post
	 *@return json,xml
	 *@author NewFuture
	 */
	public function index() 
	{
		$pwd         = I('post.pwd');
		$type        = I('post.type', null, 'int');
		$Model       = null;
		switch ($type) 
		{
		case C('STUDENT'):
		case C('STUDENT_API'):
			$account     = I('post.account', 0, '/^(\d{7}|\d{10})$/');
			$Model       = M('user');
			$where['student_number']             = $account;
			break;

		case C('PRINTER'):
		case C('PRINTER_WEB'):
			$account = I('post.account', null, '/^\w{3,16}$/');
			$Model   = M('printer');
			$where['account']         = $account;
			break;

		default:
			$data['err']       = 'unknown user type';
		}
		
		if (!isset($data)) 
		{
			if ($account) 
			{
				$key   = 'api_' . $account;
				$times = S($key);
				if ($times > C('MAX_TRIES')) 
				{
					\Think\Log::record('api爆破警告：ip:' . get_client_ip() . ',account:' . $account, 'NOTIC', true);
					$data['err'] = '此账号尝试次数过多，已经暂时封禁，请于一小时后重试！（ps:你的行为已被系统记录）';
				} else
				{
					S($key, $times + 1, 3600);
					$info     = $Model->field('id,password,name')->find();
					$id       = $info['id'];
					$password = $info['password'];
					if ($password == encode($pwd, $account)) 
					{
						$token    = update_token($id, $type);
						if ($token) 
						{
							S($key, null);
							$data['token'] = $token;
							$data['name'] = $info['name'];
							$data['id'] = $info['id'];
						} else
						{
							$data['err'] = '创建令牌失败';
						}
					} else
					{
						$data['err'] = 'authored failed';
					}
				}
			} else
			{
				$data['err'] == 'illegal account';
			}
		}
		$data['version'] = C('API_VERSION');
		$this->response($data, (($this->_type == 'xml') ? 'xml' : 'json'));
	}
	
	/**
	 *token
	 *api令牌管理
	 * 支持操作put,delete
	 *@return json,xml
	 *@author NewFuture
	 */
	public function token() 
	{
		$token = I('token', null, '/^\w{32,63}$/');
		switch ($this->_method) 
		{
		case 'delete':
			
			//删除token
			if (M('token')->where('token="%s"', md5($token))->delete() === false) 
			{
				$data['msg']       = '删除成功！';
			} else
			{
				$data['err']       = '删除失败！';
			}
			break;

		case 'put':
			
			//强制更新token
			$token = update_token($token);
			if ($token) 
			{
				$data['token']       = $token;
			} else
			{
				$data['err']       = '更新失败！';
			}
			break;

		default:
			$data['err'] = 'unkown method';
			break;
		}
		$this->response($data, (($this->_type == 'xml') ? 'xml' : 'json'));
	}
}
