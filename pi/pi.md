# 将树莓派打造成一个可以自动翻墙的无线路由器
#### 介绍：
pi有很多折腾的方法，这里介绍如何使用pi自动翻墙。
#### 原理：
将pi做成一个无线AP，通过pppoe拨号上网，ss+dnsmasq+ipset+iptables翻墙，并使它自动启动。

##1、事前准备：
* 树莓派；我使用pi2，如果是pi3会有一些细节上的不同，另外不需要额外的无线网卡。
* 4G以上的microSD卡，pi的电源线，网线，无线网卡；
* 路由器，pppoe帐号，用于无线连接的笔记本电脑；
* 了解linux的相关操作。

## 2、将raspbian系统镜像刷入树莓派的sd卡内；  
具体的步骤这里不赘述，网上教程很多。 


## 3、远程控制树莓派：  
#### 3.1、将卡插入pi并通电开机。等待pi的绿灯闪烁完毕后，用网线连接pi与路由器。  
   此时在路由器的dhcp服务器下的客户端列表里可以看到pi被分配的ip地址。  
#### 3.2、通过ssh连接到pi。用户名和密码分别是pi和raspberry。  
   我的pi的地址是192.168.1.103，在终端输入ssh -l pi 192.168.1.103，输入yes和raspberry登录。  
   如果是windows请使用putty远程登录。  
## 4、将树莓派做成一个可以发送信号的AP热点。  
因为我用的是pi2，是不自带无线网卡的；我买了一个外置的无线网卡，芯片是rtl8188cu。  
rtl8188cu的兼容问题比较麻烦，我找到了这篇文章:  
https://learn.adafruit.com/setting-up-a-raspberry-pi-as-a-wifi-access-point/overview  
写得非常详细。一步一步操作下来，最后可以搜到pi的热点。  
这篇文章用的是nano编辑器，用vim编辑器的话请先更新一下，不然会有些小bug。
sudo apt-get install vim
重启并连接上pi的热点，并重新通过ssh登录。
## 5、使用pppoe拨号上网  
#### 5、1：下载一些需要的安装包：  
`sudo apt-get install pppoe pppoeconf pppstatus ipset`  
安装完成后，将宽带网线拔出路由器并连接到pi上。
###在这里要注意先断开路由器的电源再拔掉网线，否则可能要等几分钟pi才可以拨号成功。  
#### 5、2：输入命令：  
`pppoeconf`  
根据提示输入pppoe的账号名和密码。  
#### 5、3：检测
步骤完成后输入：  
`ifconfig`  
如果有ppp0的相关信息，说明拨号成功。  
如果没有，可以根据pppoeconf的提示查看错误信息。  
## 6、设置流量转发  
连上之后pi能上网但是你的电脑是不能上网的，要设置流量转发。  
#### 6.1、root登录后在终端输入：  
<pre>
iptables -t nat -A POSTROUTING  -o ppp0    -j MASQUERADE
iptables -A  FORWARD -i ppp0 -o wlan0 -m state --state RELATED,ESTABLISHED -j ACCEPT
iptables -A FORWARD  -i wlan0   -o ppp0 -j ACCEPT
</pre>
这样你的电脑就可以上网了。
#### 6、2 注意：
这些配置重启之后会被删除。

## 7、安装翻墙软件并配置  
 
#### 7、1：ss的一键安装脚本：  
https://teddysun.com/358.html  
#### 7、2：安装dnsmasq:  
sudo apt-get install dnsmasq  
#### 7、3：修改shadowsocks的配置文件：
在/etc/shadowsocks-libev下修改config.json  
形式为：  
{  
    "server": "...",  
    "server_port": ...,  
    "local_port": 1080,  
    "password": "...",  
    "timeout": 60,  
    "method": "rc4-md5"  
}     
#### 7.4 配置dnsmasq：
编辑 /etc/dnsmasq.conf，根据自己的IP地址修改dhcp-range;  
我们之前设置的IP地址是192.168.42.1，那么配置就是:
dhcp-range=192.168.42.1  ,192.168.42.100,255.255.255.0,12h  
在这里我的配置文件内容为：  
<pre>
interface=wlan0       
server=8.8.8.8     
conf-dir=/etc/dnsmasq.d
dhcp-range=192.168.42.1,192.168.42.100,255.255.255.0,12h 
</pre>
将fuckgfw.conf放到/etc/dnsmasq.d目录下，然后重启dnsmasq。
##8、 建立shell文件并执行：  
接下来ssh到pi，在某个文件夹建立一个shell文件：比如叫gfw.sh,然后将下列命令写入文件:  
<pre>
## kill dnsmasq and  ss-redir
sudo ps -ef | grep ss-redir | grep -v grep | cut -c 9-15 | xargs kill -s 9
sudo ps -ef | grep dnsmasq | grep -v grep | cut -c 9-15 | xargs kill -s 9
#run  dnsmasq
sudo /usr/sbin/dnsmasq -C /etc/dnsmasq.conf
#redir wlan0 -> ppp0 when ppp0 is up
sudo iptables -t nat -A POSTROUTING  -o ppp0    -j MASQUERADE
sudo iptables  -A  FORWARD -i ppp0 -o wlan0 -m state --state RELATED,ESTABLISHED -j ACCEPT
sudo iptables -A FORWARD  -i wlan0   -o ppp0 -j ACCEPT
#create a ipset named  fuckgfw
sudo ipset -N fuckgfw iphash
#redir all ips match  fuckgfw to local port 1080, 1080 is defined in  config.json
sudo iptables -t nat -A PREROUTING -p tcp -m set --match-set fuckgfw dst -j REDIRECT --to-port 1080
sudoiptables -t nat -A OUTPUT -p tcp -m set --match-set fuckgfw dst -j REDIRECT --to-port 1080
# only run ss-redir,  ss-local and ss-tunnel not used.
sudo /usr/local/bin/ss-redir -u -c /etc/shadowsocks-libev/config.json -b 0.0.0.0
</pre>
#### 8、1 给文件设立权限：
在文件所在的目录内输入：sudo chmod 777 gfw.sh。
#### 8、2 启动程序：
重启后执行：./gfw.sh，你的电脑连上pi的热点后就可以翻墙。  
## 9、设置为拨号成功后自动翻墙  
为了我们可以直接用pi来翻墙而不需要远程控制pi执行脚本文件，我们可以让pi在某个端口出现后自动启动脚本程序。
在这里由于pi型号的不同，有两种方式:  
#### 9、1 在ppp0出现后启动
第一种是通过vim编辑器将上述命令插入到到  /etc/ppp/ip-up 中，在ppp0端口出现后即成功拨号后就会自动启动这些命令。
按道理来说这样pi在开机后就可以自动翻墙了，然而我的却有限制，要先将pi启动再插上网线，否则会失败。  
原因是ppp0出现的时候wlan0还没有出现，某些命令运行后不能达到想要的效果。
如果我先开机再拨号，此时wlan0已经出现了，命令是可以成功执行的。
这情况应该与pi的型号有关。
pi3估计是没有这个问题的，不过我没有pi3去测试。  
#### 9、2 在wlan0出现后启动
我使用了第二种方法，在某个目录下创建一个文件，比如说是 /gfw.sh，即在 / 目录下建立gfw.sh文件。  
将翻墙命令复制进入文件中并保存，给文件加上权限；  
用vi编辑器编辑 /etc/network/intefaces文件，在wlan0的相关配置后加上一行：  
post-up /gfw.sh  
经过测试，我的电脑连接pi2的热点是可以上网的。  
<br />
<br />  
<br />
<br />
<br />
<br />
* * * 
# 写在最后
### 感谢：
* 博学多才的唐永进先生  
  没有他的帮助就不可能有这篇文章。  
* http://learn.adafruit.com/setting-up-a-raspberry-pi-as-a-wifi-access-point/overview  
* https://teddysun.com/358.html  
* https://wiki.archlinux.org/index.php/PPTP_Client_(简体中文)  
* https://www.raspberrypi.org/forums/viewtopic.php?f=36&t=78734  
