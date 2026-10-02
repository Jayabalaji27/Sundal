<?php $__env->startSection('title', trans('installer_messages.updater.overview.title')); ?>
<?php $__env->startSection('container'); ?>
    <div class="text-center">
        <div class="mb-8">
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-list text-3xl text-green-600"></i>
            </div>
            <h2 class="text-2xl font-semibold text-gray-900 mb-4">Update Overview</h2>
            <p class="text-gray-600 max-w-md mx-auto leading-relaxed">
                <?php echo e(trans_choice('installer_messages.updater.overview.message', $numberOfUpdatesPending, ['number' => $numberOfUpdatesPending])); ?>

            </p>
        </div>
        
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <div class="flex items-center justify-center">
                <i class="fas fa-info-circle text-green-600 mr-2"></i>
                <span class="text-green-800 font-medium"><?php echo e($numberOfUpdatesPending); ?> update(s) pending</span>
            </div>
        </div>
        
        <a href="<?php echo e(route('LaravelUpdater::database')); ?>" class="inline-flex items-center px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors duration-200">
            <i class="fas fa-download mr-2"></i>
            <?php echo e(trans('installer_messages.updater.overview.install_updates')); ?>

        </a>
    </div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('vendor.installer.layouts.master-update', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/vetridev/htdocs/168-231-102-225.sslip.io/resources/views/vendor/installer/update/overview.blade.php ENDPATH**/ ?>