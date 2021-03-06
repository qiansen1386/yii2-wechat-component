<?php
/**
 * Created by PhpStorm.
 * User: 俊杰
 * Date: 14-8-30
 * Time: 上午11:17
 */

namespace iit\wechat;


class OAuth
{
    const BASE_AUTH = 'snsapi_base';
    const USER_INFO_AUTH = 'snsapi_userinfo';
    const OAUTH_URL = 'oauth_url';
    const REFRESH_TOKEN_URL = 'oauth_refresh_token';
    const ACCESS_TOKEN_URL = 'oauth_access_token';

    /**
     * 通过session存放openid的key值
     */

    const OPENID_SESSION_KEY = 'oauth_openid';

    /**
     * 缓存刷新token的key后缀
     */
    const REFRESH_TOKEN_CACHE = '_oauth_refresh_token';

    /**
     * 缓存访问token的key后缀
     */
    const ACCESS_TOKEN_CACHE = '_oauth_access_token';

    private $_openid;
    private $_accessToken;
    private $_refreshToken;

    /**
     * @return mixed|null
     */

    public function getAccessToken()
    {
        if ($this->_accessToken === null) {
            if ($cacheAccessToken = Wechat::getCache($this->getOpenid() . self::ACCESS_TOKEN_CACHE)) {
                $this->_accessToken = $cacheAccessToken;
            } else {
                if ($result = $this->httpAccessTokenByRefreshToken()) {
                    $this->setAccessToken($result['access_token'], $result['expires_in']);
                } else {
                    if ($result = $this->httpAccessTokenByCode()) {
                        $this->setAccessToken($result['access_token'], $result['expires_in']);
                    } else {
                        return false;
                    }
                }
            }
        }
        return $this->_accessToken;
    }

    /**
     * 设置访问token的各种缓存
     * @param $token
     * @param $duration
     * @return bool
     */

    public function setAccessToken($token, $duration)
    {
        $this->_accessToken = $token;
        return Wechat::setCache($this->getOpenid() . self::ACCESS_TOKEN_CACHE, $token, $duration);
    }

    /**
     * 获取openid，优先从缓存里读取
     * @return bool|mixed|null
     */

    public function getOpenid()
    {
        if ($this->_openid === null) {
            if ($sessionOpenid = \Yii::$app->session->get(self::OPENID_SESSION_KEY)) {
                $this->_openid = $sessionOpenid;
            } else {
                $result = $this->httpAccessTokenByCode();
                if ($result) {
                    isset($result['openid']) && $this->setOpenid($result['openid']);
                } else {
                    return false;
                }
            }
        }
        return $this->_openid;
    }

    /**
     * 设置openid
     * @param $openid
     */

    public function setOpenid($openid)
    {
        $this->_openid = $openid;
        \Yii::$app->session->set(self::OPENID_SESSION_KEY, $openid);
    }

    /**
     * 通过验证码从微信服务器获取访问token
     * @return bool|mixed
     */

    public function httpAccessTokenByCode()
    {
        if ($code = $this->getCode()) {
            $result = Wechat::httpGet(Url::get(self::ACCESS_TOKEN_URL), [
                'appid' => Wechat::$component->appid,
                'secret' => Wechat::$component->appsecret,
                'code' => $code
            ], false);
            if ($result && !isset($result['errcode'])) {
                return $result;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 获取从鉴权地址跳转回来时带上的验证码
     * @return bool
     */

    public function getCode()
    {
        return \Yii::$app->request->get('code') ?: false;
    }

    /**
     * 通过刷新token从微信服务器获取访问token
     * @return bool
     */

    public function httpAccessTokenByRefreshToken()
    {
        if ($refreshToken = $this->getRefreshToken()) {
            $result = Wechat::httpGet(Url::get(self::REFRESH_TOKEN_URL), [
                'appid' => Wechat::$component->appid,
                'refresh_token' => $refreshToken
            ], false);
            if ($result && !isset($result['errcode'])) {
                $this->setRefreshToken($result['refresh_token']);
                return $result;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 获取刷新token
     * @return bool|mixed
     */

    public function getRefreshToken()
    {
        if ($this->_refreshToken === null) {
            $cacheKey = $this->getOpenid() . self::REFRESH_TOKEN_CACHE;
            if ($cacheRefreshToken = Wechat::getCache($cacheKey)) {
                $this->_refreshToken = $cacheRefreshToken;
            } else {
                return false;
            }
        }
        return $this->_refreshToken;
    }

    /**
     * 设置刷新token
     * @param $token
     * @return bool
     */

    public function setRefreshToken($token)
    {
        $this->_refreshToken = $token;
        return Wechat::setCache($this->getOpenid() . self::REFRESH_TOKEN_CACHE, $token);
    }

    /**
     * 组装OAuth2.0鉴权地址
     * @param null $state
     * @return string
     */

    public function getOAuthUrl($type, $state = null)
    {
        return Url::get(self::OAUTH_URL) . '?' . http_build_query([
            'appid' => Wechat::$component->appid,
            'redirect_uri' => \Yii::$app->request->absoluteUrl,
            'response_type' => 'code',
            'scope' => $type,
            'state' => $state
        ]) . '#wechat_redirect';
    }

} 