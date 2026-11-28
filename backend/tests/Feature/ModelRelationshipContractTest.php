<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AgencyInvitation;
use App\Models\AgencyMember;
use App\Models\AgencyPromotion;
use App\Models\AgencySubscription;
use App\Models\AgencyVideo;
use App\Models\AuditLog;
use App\Models\Block;
use App\Models\Commission;
use App\Models\Dealer;
use App\Models\DealerFile;
use App\Models\Document;
use App\Models\DocumentAccessLog;
use App\Models\DocumentFolder;
use App\Models\DocumentQuarantine;
use App\Models\DocumentShare;
use App\Models\DocumentVersion;
use App\Models\Expense;
use App\Models\Inquiry;
use App\Models\LeadActivity;
use App\Models\LoginHistory;
use App\Models\Map;
use App\Models\MediaFile;
use App\Models\PaymentSchedule;
use App\Models\Permission;
use App\Models\Project;
use App\Models\PromotionPackage;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\PropertyFavorite;
use App\Models\PropertyStatusHistory;
use App\Models\PropertyView;
use App\Models\Role;
use App\Models\SavedSearch;
use App\Models\Sector;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Models\User;
use App\Models\VideoComment;
use App\Models\VideoLike;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Tests\TestCase;

class ModelRelationshipContractTest extends TestCase
{
    /**
     * Validate relation methods without requiring fixture rows. This catches
     * misspelled foreign keys, wrong related models and missing relationship
     * methods even when list endpoints happen to return empty collections.
     */
    public function test_declared_eloquent_relationships_resolve_to_expected_models(): void
    {
        $contracts = [
            [Agency::class, 'members', HasMany::class, AgencyMember::class],
            [Agency::class, 'activeMembers', HasMany::class, AgencyMember::class],
            [Agency::class, 'owner', BelongsTo::class, User::class],
            [Agency::class, 'properties', HasMany::class, Property::class],
            [Agency::class, 'documents', HasMany::class, Document::class],
            [Agency::class, 'mediaFiles', HasMany::class, MediaFile::class],
            [Agency::class, 'inquiries', HasMany::class, Inquiry::class],
            [Agency::class, 'transactions', HasMany::class, Transaction::class],
            [Agency::class, 'subscription', HasOne::class, AgencySubscription::class],
            [Agency::class, 'promotions', HasMany::class, AgencyPromotion::class],
            [Agency::class, 'activePromotion', HasOne::class, AgencyPromotion::class],
            [Agency::class, 'invitations', HasMany::class, AgencyInvitation::class],
            [Agency::class, 'documentFolders', HasMany::class, DocumentFolder::class],
            [Agency::class, 'documentShares', HasMany::class, DocumentShare::class],
            [Agency::class, 'documentAccessLogs', HasMany::class, DocumentAccessLog::class],
            [Agency::class, 'quarantinedDocuments', HasMany::class, DocumentQuarantine::class],
            [Agency::class, 'leadActivities', HasMany::class, LeadActivity::class],
            [Agency::class, 'transactionPayments', HasMany::class, TransactionPayment::class],
            [Agency::class, 'paymentSchedules', HasMany::class, PaymentSchedule::class],
            [Agency::class, 'commissions', HasMany::class, Commission::class],
            [Agency::class, 'expenses', HasMany::class, Expense::class],
            [Agency::class, 'subscriptionInvoices', HasMany::class, SubscriptionInvoice::class],
            [Agency::class, 'supportTickets', HasMany::class, SupportTicket::class],
            [Agency::class, 'auditLogs', HasMany::class, AuditLog::class],
            [Agency::class, 'propertyStatusHistory', HasMany::class, PropertyStatusHistory::class],

            [AgencyMember::class, 'agency', BelongsTo::class, Agency::class],
            [AgencyMember::class, 'user', BelongsTo::class, User::class],
            [AgencyMember::class, 'role', BelongsTo::class, Role::class],
            [AgencyInvitation::class, 'agency', BelongsTo::class, Agency::class],
            [AgencyInvitation::class, 'role', BelongsTo::class, Role::class],
            [AgencyInvitation::class, 'invitedBy', BelongsTo::class, User::class],
            [AgencyPromotion::class, 'agency', BelongsTo::class, Agency::class],
            [AgencyPromotion::class, 'package', BelongsTo::class, PromotionPackage::class],
            [AgencyPromotion::class, 'creator', BelongsTo::class, User::class],
            [AgencyPromotion::class, 'approver', BelongsTo::class, User::class],
            [AgencySubscription::class, 'agency', BelongsTo::class, Agency::class],
            [AgencySubscription::class, 'plan', BelongsTo::class, SubscriptionPlan::class],
            [AgencySubscription::class, 'creator', BelongsTo::class, User::class],
            [Role::class, 'permissionsCatalog', BelongsToMany::class, Permission::class],
            [Role::class, 'members', HasMany::class, AgencyMember::class],
            [Role::class, 'agency', BelongsTo::class, Agency::class],
            [Role::class, 'creator', BelongsTo::class, User::class],
            [Permission::class, 'roles', BelongsToMany::class, Role::class],

            [Property::class, 'assignedDealer', BelongsTo::class, Dealer::class],
            [Property::class, 'agency', BelongsTo::class, Agency::class],
            [Property::class, 'creator', BelongsTo::class, User::class],
            [Property::class, 'sectorRelation', BelongsTo::class, Sector::class],
            [Property::class, 'blockRelation', BelongsTo::class, Block::class],
            [Property::class, 'category', BelongsTo::class, PropertyCategory::class],
            [Property::class, 'statusHistory', HasMany::class, PropertyStatusHistory::class],
            [Property::class, 'documents', HasMany::class, Document::class],
            [Property::class, 'mediaFiles', HasMany::class, MediaFile::class],
            [Property::class, 'inquiries', HasMany::class, Inquiry::class],
            [Property::class, 'views', HasMany::class, PropertyView::class],
            [PropertyCategory::class, 'properties', HasMany::class, Property::class],
            [Sector::class, 'blocks', HasMany::class, Block::class],
            [Sector::class, 'properties', HasMany::class, Property::class],
            [Block::class, 'sector', BelongsTo::class, Sector::class],
            [Block::class, 'properties', HasMany::class, Property::class],

            [Inquiry::class, 'agency', BelongsTo::class, Agency::class],
            [Inquiry::class, 'property', BelongsTo::class, Property::class],
            [Inquiry::class, 'assignedAgent', BelongsTo::class, User::class],
            [Inquiry::class, 'activities', HasMany::class, LeadActivity::class],
            [LeadActivity::class, 'inquiry', BelongsTo::class, Inquiry::class],
            [LeadActivity::class, 'actor', BelongsTo::class, User::class],
            [LeadActivity::class, 'agency', BelongsTo::class, Agency::class],

            [Document::class, 'agency', BelongsTo::class, Agency::class],
            [Document::class, 'folder', BelongsTo::class, DocumentFolder::class],
            [Document::class, 'property', BelongsTo::class, Property::class],
            [Document::class, 'uploader', BelongsTo::class, User::class],
            [Document::class, 'versions', HasMany::class, DocumentVersion::class],
            [Document::class, 'accessLogs', HasMany::class, DocumentAccessLog::class],
            [DocumentFolder::class, 'agency', BelongsTo::class, Agency::class],
            [DocumentFolder::class, 'parent', BelongsTo::class, DocumentFolder::class],
            [DocumentFolder::class, 'children', HasMany::class, DocumentFolder::class],
            [DocumentFolder::class, 'documents', HasMany::class, Document::class],
            [DocumentFolder::class, 'property', BelongsTo::class, Property::class],
            [DocumentFolder::class, 'creator', BelongsTo::class, User::class],
            [DocumentVersion::class, 'document', BelongsTo::class, Document::class],
            [DocumentVersion::class, 'uploader', BelongsTo::class, User::class],
            [DocumentShare::class, 'document', BelongsTo::class, Document::class],
            [DocumentShare::class, 'agency', BelongsTo::class, Agency::class],
            [DocumentShare::class, 'creator', BelongsTo::class, User::class],
            [DocumentAccessLog::class, 'document', BelongsTo::class, Document::class],
            [DocumentAccessLog::class, 'user', BelongsTo::class, User::class],
            [DocumentAccessLog::class, 'agency', BelongsTo::class, Agency::class],
            [DocumentQuarantine::class, 'agency', BelongsTo::class, Agency::class],
            [DocumentQuarantine::class, 'document', BelongsTo::class, Document::class],
            [DocumentQuarantine::class, 'uploader', BelongsTo::class, User::class],
            [MediaFile::class, 'agency', BelongsTo::class, Agency::class],
            [MediaFile::class, 'property', BelongsTo::class, Property::class],
            [MediaFile::class, 'uploader', BelongsTo::class, User::class],

            [Transaction::class, 'businessRecord', HasOne::class, \App\Models\BusinessRecord::class],
            [Transaction::class, 'property', BelongsTo::class, Property::class],
            [Transaction::class, 'dealer', BelongsTo::class, Dealer::class],
            [Transaction::class, 'agency', BelongsTo::class, Agency::class],
            [Transaction::class, 'creator', BelongsTo::class, User::class],
            [Transaction::class, 'updater', BelongsTo::class, User::class],
            [Transaction::class, 'voider', BelongsTo::class, User::class],
            [Transaction::class, 'approver', BelongsTo::class, User::class],
            [Transaction::class, 'payments', HasMany::class, TransactionPayment::class],
            [Transaction::class, 'schedules', HasMany::class, PaymentSchedule::class],
            [Transaction::class, 'commissions', HasMany::class, Commission::class],
            [TransactionPayment::class, 'transaction', BelongsTo::class, Transaction::class],
            [TransactionPayment::class, 'agency', BelongsTo::class, Agency::class],
            [TransactionPayment::class, 'recorder', BelongsTo::class, User::class],
            [PaymentSchedule::class, 'transaction', BelongsTo::class, Transaction::class],
            [PaymentSchedule::class, 'agency', BelongsTo::class, Agency::class],
            [Commission::class, 'transaction', BelongsTo::class, Transaction::class],
            [Commission::class, 'dealer', BelongsTo::class, Dealer::class],
            [Commission::class, 'agency', BelongsTo::class, Agency::class],
            [Commission::class, 'beneficiary', BelongsTo::class, User::class],
            [Commission::class, 'paidBy', BelongsTo::class, User::class],

            [SavedSearch::class, 'user', BelongsTo::class, User::class],
            [SubscriptionPlan::class, 'subscriptions', HasMany::class, AgencySubscription::class],
            [SubscriptionInvoice::class, 'agency', BelongsTo::class, Agency::class],
            [SubscriptionInvoice::class, 'plan', BelongsTo::class, SubscriptionPlan::class],
            [SubscriptionInvoice::class, 'verifier', BelongsTo::class, User::class],
            [SupportTicket::class, 'agency', BelongsTo::class, Agency::class],
            [SupportTicket::class, 'creator', BelongsTo::class, User::class],
            [SupportTicket::class, 'assignee', BelongsTo::class, User::class],
            [SupportTicket::class, 'messages', HasMany::class, SupportMessage::class],
            [SupportMessage::class, 'ticket', BelongsTo::class, SupportTicket::class],
            [SupportMessage::class, 'author', BelongsTo::class, User::class],
            [AgencyVideo::class, 'comments', HasMany::class, VideoComment::class],
            [VideoComment::class, 'video', BelongsTo::class, AgencyVideo::class],
            [VideoComment::class, 'user', BelongsTo::class, User::class],
            [VideoLike::class, 'video', BelongsTo::class, AgencyVideo::class],
            [VideoLike::class, 'user', BelongsTo::class, User::class],
            [PropertyFavorite::class, 'user', BelongsTo::class, User::class],
            [PropertyFavorite::class, 'property', BelongsTo::class, Property::class],
            [PropertyStatusHistory::class, 'property', BelongsTo::class, Property::class],
            [PropertyStatusHistory::class, 'agency', BelongsTo::class, Agency::class],
            [PropertyStatusHistory::class, 'actor', BelongsTo::class, User::class],
            [PropertyView::class, 'property', BelongsTo::class, Property::class],
            [PropertyView::class, 'user', BelongsTo::class, User::class],
            [Map::class, 'creator', BelongsTo::class, User::class],
            [Map::class, 'updater', BelongsTo::class, User::class],
            [Expense::class, 'agency', BelongsTo::class, Agency::class],
            [Expense::class, 'creator', BelongsTo::class, User::class],
            [DealerFile::class, 'dealer', BelongsTo::class, Dealer::class],
            [DealerFile::class, 'uploadedBy', BelongsTo::class, Dealer::class],
            [DealerFile::class, 'soldBy', BelongsTo::class, Dealer::class],
            [AuditLog::class, 'user', BelongsTo::class, User::class],
            [AuditLog::class, 'agency', BelongsTo::class, Agency::class],
            [LoginHistory::class, 'user', BelongsTo::class, User::class],
            [PromotionPackage::class, 'promotions', HasMany::class, AgencyPromotion::class],
            [Dealer::class, 'user', HasOne::class, User::class],
            [Dealer::class, 'properties', HasMany::class, Property::class],
            [Dealer::class, 'transactions', HasMany::class, Transaction::class],
            [Dealer::class, 'dealerFiles', HasMany::class, DealerFile::class],
            [User::class, 'memberships', HasMany::class, AgencyMember::class],
            [User::class, 'activeMemberships', HasMany::class, AgencyMember::class],
            [User::class, 'dealer', BelongsTo::class, Dealer::class],
            [User::class, 'loginHistories', HasMany::class, LoginHistory::class],
        ];

        foreach ($contracts as [$modelClass, $method, $relationClass, $relatedClass]) {
            $model = new $modelClass();

            $this->assertTrue(
                method_exists($model, $method),
                "{$modelClass}::{$method} relationship method is missing."
            );

            $relation = $model->{$method}();

            $this->assertInstanceOf(
                $relationClass,
                $relation,
                "{$modelClass}::{$method} must return {$relationClass}."
            );

            $this->assertInstanceOf(
                $relatedClass,
                $relation->getRelated(),
                "{$modelClass}::{$method} must target {$relatedClass}."
            );
        }
    }
}
