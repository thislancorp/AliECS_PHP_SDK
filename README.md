AliECS_PHP_SDK
==============

阿里云主机 ECS PHP SDK 开发包


重要提醒 本项目已经迁移至https://github.com/hexdata/AliECS_PHP_SDK
若您想使用本SDK开发新项目，请进入新地址获取SDK代码。

迁移与更新原因分析：
因设计上的失败，ECS::下各个方法均使用了独立变量，导致ECS::somefunc('instanceId','','','otherparam')执行容易产生歧义，不利于SDK与项目维护，加之thislan项目的终止，thislancorp用户已弃用，所以迁移项目并重构了一次。以前使用此SDK开发项目的开发者，enj0y在此郑重道歉~，需要用此SDK开发新项目的开发者，请务必进新Github项目页面同步新的代码。

传参机制变更说明：
以前的SDK执行方式 ECS::startInstance($instanceId)
现在的执行方式    ECS::startInstance( array('InstanceId' => $instanceId) )


  2013-05-14 开始项目
  
  2013-05-16 修复ISSUE#1/#2的错误 删除暂时未开放的创建实例的接口

  2013-09-22 项目迁移 重构SDK类中方法传参机制
