<?php

class shopUpdateproductsPluginBackendGetFeatureValuesController extends waJsonController
{

    public function execute()
    {
        try {
            $feature_id = waRequest::post('feature_id', 0, waRequest::TYPE_INT);

            $feature_model = new shopFeatureModel();
            $features = $feature_model->getByField('id', $feature_id, 'id');
            $feature = array();
            if ($features) {
                $features = $feature_model->getValues($features);
                $feature = array_shift($features);
            }

            $profile_helper = new shopImportexportHelper('updateproducts');
            $profile_helper->getList();
            $profile = $profile_helper->getConfig();

            $view = wa()->getView();
            $view->assign(array(
                'profile' => $profile,
                'feature' => $feature,
            ));
            $this->response['html'] = $view->fetch(wa()->getAppPath('/plugins/updateproducts/templates/actions/backend/include.featureValues.html', 'shop'));
        } catch (Exception $ex) {
            $this->setError($ex->getMessage());
        }
    }

}
