# GitHub 构建与 1Panel 自动部署

项目包含三条 GitHub Actions 流程：

- `CI`：提交或 Pull Request 时安装 PHP/前端依赖、构建 Vite 资源并执行测试。
- `Release Package`：手动运行或推送 `v*` 标签时生成可直接上传服务器的 `yopay-release.zip`。
- `Deploy Production`：`main` 分支的 CI 成功后，通过 SSH 和 `rsync` 更新服务器，再进入 1Panel PHP 容器执行安全迁移与缓存清理。

## 一、推送到 GitHub

首次提交只需要初始化现有项目，不要覆盖当前 `README.md`：

```bash
git init
git add .
git commit -m "Initial YoPay release"
git branch -M main
git remote add origin https://github.com/cuser-it/yopay.git
git push -u origin main
```

后续更新：

```bash
git add .
git commit -m "Fix checkout recovery and QR rendering"
git push
```

## 二、在 GitHub 生成发布包

进入仓库的 **Actions → Release Package → Run workflow**。构建完成后，在该次任务的 Artifacts 区域下载 `yopay-release`。

也可以创建版本标签：

```bash
git tag v1.0.0
git push origin v1.0.0
```

标签会自动创建 GitHub Release 并附带 `yopay-release.zip`。

发布包包含生产 `vendor/` 和 `public/build/`，不包含：

- `.env`、安装锁和运行日志；
- `node_modules/`；
- `tests/` 和开发依赖；
- Git 历史与旧的 `dist/` 文件。

由于 Laravel 的生产依赖位于 `vendor/`，完整免 Composer 发布包通常仍有数十 MB；这不是测试文件导致的。源码包可以更小，但服务器必须自行执行 Composer。

## 三、首次服务器准备

现有 1Panel 环境使用：

```text
宿主机目录：/opt/1panel/www/sites/yopay.yunvix.com/index
PHP 容器：Yunvix_Pay
容器目录：/www/sites/yopay.yunvix.com/index
```

确保部署用户可以：

- 通过 SSH 登录；
- 写入宿主机项目目录；
- 执行 `docker exec Yunvix_Pay ...`；
- 遍历项目目录，项目根目录权限不能是 `700 root:root`。

首次安装仍通过 `/install` 完成。自动部署只负责后续代码更新，不会覆盖 `.env`、`storage/`、`storage/app/private/installed.lock` 或数据库。

## 四、配置 GitHub Environment

在 **Settings → Environments** 创建 `production`。建议启用 Required reviewers，使每次生产部署需要人工批准。

在 `production` 的 Secrets 中添加：

| Secret | 示例 |
| --- | --- |
| `DEPLOY_HOST` | `yopay.yunvix.com` 或服务器 IP |
| `DEPLOY_PORT` | `22` |
| `DEPLOY_USER` | 专用部署用户 |
| `DEPLOY_SSH_KEY` | 部署用户私钥全文 |
| `DEPLOY_KNOWN_HOSTS` | 服务器固定 SSH host key |
| `DEPLOY_PATH` | `/opt/1panel/www/sites/yopay.yunvix.com/index` |
| `PHP_CONTAINER` | `Yunvix_Pay` |
| `PHP_CONTAINER_PATH` | `/www/sites/yopay.yunvix.com/index` |

生成专用部署密钥：

```bash
ssh-keygen -t ed25519 -C "github-actions-yopay" -f ~/.ssh/yopay_deploy
```

将 `yopay_deploy.pub` 加入服务器部署用户的 `~/.ssh/authorized_keys`，将私钥内容保存到 `DEPLOY_SSH_KEY`。

固定服务器 host key：

```bash
ssh-keyscan -H -p 22 yopay.yunvix.com
```

确认输出指纹后，将完整输出保存到 `DEPLOY_KNOWN_HOSTS`，不要在工作流中临时信任未知 host key。

可在 Environment Variables 中增加：

```text
DEPLOY_HEALTHCHECK_URL=https://yopay.yunvix.com/up
```

## 五、自动部署行为

每次推送 `main`：

1. `CI` 完成 PHP 测试和 Vite 构建。
2. 只有 CI 成功，`Deploy Production` 才会启动。
3. GitHub 重新生成生产依赖和前端资源。
4. `rsync --delete` 删除服务器上的过期代码，但明确保留 `.env`、`storage/`、`bootstrap/cache/` 和 `public/storage`。
5. 容器内执行：

```bash
php artisan migrate --force
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan queue:restart
```

迁移不会执行 `migrate:fresh`、删表或覆盖数据库。新迁移必须继续兼容目标 MySQL 版本。

## 六、手动发布

如果暂时不启用 CD，可下载 GitHub 生成的发布包，解压后上传覆盖项目代码，但必须保留：

```text
.env
storage/
bootstrap/cache/
public/storage
```

上传后执行：

```bash
docker exec -it Yunvix_Pay /bin/bash -lc '
cd /www/sites/yopay.yunvix.com/index
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
php artisan migrate --force
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan queue:restart
'
```

最后访问 `/up`、首页和一个测试订单，确认页面、二维码、回调与队列均正常。
