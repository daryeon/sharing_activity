/*
 Navicat Premium Data Transfer

 Source Server         : 虚拟机
 Source Server Type    : MySQL
 Source Server Version : 50724
 Source Host           : 192.168.56.101:3306
 Source Schema         : thinkcmf51

 Target Server Type    : MySQL
 Target Server Version : 50724
 File Encoding         : 65001

 Date: 26/03/2019 15:51:15
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for cmf_plugin_chat_user
-- ----------------------------
DROP TABLE IF EXISTS `cmf_plugin_chat_user`;
CREATE TABLE `cmf_plugin_chat_user`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NULL DEFAULT NULL COMMENT '用户id',
  `chat_id` int(11) NULL DEFAULT NULL COMMENT '聊天临时id',
  `nickname` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '用户昵称',
  `type` tinyint(1) NULL DEFAULT 1 COMMENT '1上线0下线',
  `last_login_ip` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '最后登录ip',
  `last_login_time` int(11) NOT NULL DEFAULT 0 COMMENT '最后登录时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 20 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

SET FOREIGN_KEY_CHECKS = 1;
