<?php
class shopUpdateproductsPluginBackendSetupAction extends waViewAction
{


    public function execute()
    {
        $plugin = wa()->getPlugin('updateproducts');
        $settings = $plugin->getSettings();
        $stock_model = new shopStockModel();
        $stocks = $stock_model->getAll();
        
        $this->view->assign('settings', $settings);
        $this->view->assign('stocks', $stocks);
    }
}
