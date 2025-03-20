// ... existing code ...
                    <div class="card-body">
                        <h5 class="card-title">分站信息</h5>
                        <p class="card-text">域名：<?php echo htmlspecialchars($sub_site['domain']); ?></p>
                        <p class="card-text">到期时间：<?php echo date('Y-m-d H:i:s', strtotime($sub_site['expire_time'])); ?></p>
                        <p class="card-text">状态：<?php echo $sub_site['status'] ? '正常' : '已禁用'; ?></p>
                        <p class="card-text">支付方式：<?php echo $sub_site['use_main_payment'] ? '使用主站支付' : '使用自定义支付'; ?></p>
                    </div>
                    <div class="card-footer">
                        <a href="sub_site_payment.php" class="btn btn-primary">支付设置</a>
                        <?php if (strtotime($sub_site['expire_time']) > time()): ?>
                            <a href="sub_site_renew.php" class="btn btn-success">续费</a>
                        <?php endif; ?>
                    </div>
// ... existing code ...