<?php

declare(strict_types=1);

namespace Myca\Core\Observer;

use Magento\Catalog\Model\Product;
use Magento\Config\Model\Config\Backend\Encrypted;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\Config\Value as ConfigValue;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Myca\Core\Model\AuditLogger;

/**
 * Événements Magento consignés dans le journal d'audit : connexions admin, configuration, prix et statuts produits,
 * états de commande, avoirs, comptes administrateurs et suppressions de clients.
 */
class AuditEvents implements ObserverInterface
{
    private const PRODUCT_FIELDS = ['price', 'special_price', 'special_from_date', 'special_to_date', 'cost', 'status'];

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        match ($event->getName()) {
            'backend_auth_user_login_success' => $this->audit->log('admin_login', 'admin_user', null, null, null, 'Connexion réussie'),
            'backend_auth_user_login_failed' => $this->audit->log(
                'admin_login_failed',
                'admin_user',
                (string) $event->getData('user_name'),
                null,
                null,
                'Échec de connexion'
            ),
            'config_data_save_after' => $this->config($event->getData('data_object')),
            'catalog_product_save_after' => $this->product($event->getData('product')),
            'sales_order_save_after' => $this->order($event->getData('order')),
            'sales_order_creditmemo_save_after' => $this->creditmemo($event->getData('creditmemo')),
            'admin_user_save_after' => $this->adminUser($event->getData('object'), 'admin_user_saved'),
            'admin_user_delete_after' => $this->adminUser($event->getData('object'), 'admin_user_deleted'),
            'customer_delete_after_data_object' => $this->customer($event->getData('customer_data_object')),
            default => null,
        };
    }

    private function config(mixed $value): void
    {
        if (!$value instanceof ConfigValue || !$value->isValueChanged()) {
            return;
        }
        $secret = $value instanceof Encrypted;
        $this->audit->log(
            'config_changed',
            'config',
            $value->getPath() . ' [' . $value->getScope() . ':' . $value->getScopeId() . ']',
            ['value' => $secret ? '******' : $value->getOldValue()],
            ['value' => $secret ? '******' : $value->getValue()]
        );
    }

    private function product(mixed $product): void
    {
        if (!$product instanceof Product || $product->isObjectNew()) {
            return;
        }
        $old = $new = [];
        foreach (self::PRODUCT_FIELDS as $field) {
            $before = $product->getOrigData($field);
            $after = $product->getData($field);
            if ($product->hasData($field) && (string) $before !== (string) $after && !(is_numeric($before) && is_numeric($after) && (float) $before === (float) $after)) {
                $old[$field] = $before;
                $new[$field] = $after;
            }
        }
        if ($new !== []) {
            $this->audit->log('product_changed', 'product', (string) $product->getSku(), $old, $new);
        }
    }

    private function order(mixed $order): void
    {
        if (!$order instanceof Order || $order->isObjectNew()) {
            return;
        }
        $old = ['state' => $order->getOrigData('state'), 'status' => $order->getOrigData('status')];
        $new = ['state' => $order->getState(), 'status' => $order->getStatus()];
        if ($old['status'] !== null && $old != $new) {
            $this->audit->log('order_status_changed', 'order', (string) $order->getIncrementId(), $old, $new);
        }
    }

    private function creditmemo(mixed $creditmemo): void
    {
        if (!$creditmemo instanceof Creditmemo || $creditmemo->getOrigData('entity_id') !== null) {
            return;
        }
        $this->audit->log('refund', 'order', (string) $creditmemo->getOrder()->getIncrementId(), null, [
            'creditmemo' => $creditmemo->getIncrementId(),
            'amount' => (float) $creditmemo->getGrandTotal(),
            'online' => (bool) $creditmemo->getInvoice()?->getTransactionId(),
        ]);
    }

    private function adminUser(mixed $user, string $action): void
    {
        if (!$user instanceof AbstractModel) {
            return;
        }
        $old = ['is_active' => $user->getOrigData('is_active'), 'email' => $user->getOrigData('email')];
        $new = $action === 'admin_user_deleted' ? null : ['is_active' => $user->getData('is_active'), 'email' => $user->getData('email')];
        $this->audit->log($action, 'admin_user', (string) $user->getData('username'), $old, $new);
    }

    private function customer(mixed $customer): void
    {
        if ($customer instanceof CustomerInterface) {
            $this->audit->log('customer_deleted', 'customer', (string) $customer->getId(), null, null, 'Compte client supprimé');
        }
    }
}
