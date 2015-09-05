<?php
namespace Inbep\Silex\Provider;

use Silex\Application;
use Silex\ServiceProviderInterface;
use Lexik\Bundle\FormFilterBundle\Filter\FilterBuilderUpdater;
use Lexik\Bundle\FormFilterBundle\Filter\Form\Type;
use Lexik\Bundle\FormFilterBundle\Filter\Form\FilterTypeExtension;
use Lexik\Bundle\FormFilterBundle\Filter\DataExtractor\FormDataExtractor;
use Lexik\Bundle\FormFilterBundle\Filter\DataExtractor\Method\DefaultExtractionMethod;
use Lexik\Bundle\FormFilterBundle\Filter\DataExtractor\Method\TextExtractionMethod;
use Lexik\Bundle\FormFilterBundle\Filter\DataExtractor\Method\ValueKeysExtractionMethod;
use Lexik\Bundle\FormFilterBundle\Event\Listener\PrepareListener;
use Lexik\Bundle\FormFilterBundle\Event\Listener\DoctrineApplyFilterListener;
use Lexik\Bundle\FormFilterBundle\Event\Subscriber\DoctrineORMSubscriber;
use Lexik\Bundle\FormFilterBundle\Event\Subscriber\DoctrineDBALSubscriber;

/**
 * @author Sérgio Rafael Siqueira <sergio@inbep.com.br>
 */
class FormFilterServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Application $app
     */
    public function register(Application $app)
    {
        if (!isset($app['form.factory'])) {
            throw new \LogicException('You must register the FormServiceProvider to use the FormFilterServiceProvider');
        }
        
        $app['lexik_form_filter.query_builder_updater'] = $app->share(function () use ($app) {
            return new FilterBuilderUpdater($app['lexik_form_filter.form_data_extractor'], $app['dispatcher']);
        });

        // Alias
        $app['form_filter'] = $app->share(function () use ($app) {
            return $app['lexik_form_filter.query_builder_updater'];
        });

        // Filter Types
        $app['form.types'] = $app->share($app->extend('form.types', function ($types) use ($app) {
            $types[] = new Type\TextFilterType();
            $types[] = new Type\NumberFilterType();
            $types[] = new Type\NumberRangeFilterType();
            $types[] = new Type\CheckboxFilterType();
            $types[] = new Type\BooleanFilterType();
            $types[] = new Type\ChoiceFilterType();
            $types[] = new Type\EntityFilterType($app['doctrine']);
            $types[] = new Type\DateFilterType();
            $types[] = new Type\DateRangeFilterType();
            $types[] = new Type\DateTimeFilterType();
            $types[] = new Type\DateTimeRangeFilterType();
            $types[] = new Type\CollectionAdapterFilterType();
            $types[] = new Type\SharedableFilterType();
            return $types;
        }));

        // Type extension
        $app['form.type.extensions'] = $app->share($app->extend('form.type.extensions', function ($extensions) {
            $extensions[] = new FilterTypeExtension();
            return $extensions;
        }));

        // Form data extraction
        $app['lexik_form_filter.form_data_extractor'] = $app->share(function () use ($app) {
            $extractor = new FormDataExtractor();
            $extractor->addMethod(new DefaultExtractionMethod());
            $extractor->addMethod(new TextExtractionMethod());
            $extractor->addMethod(new ValueKeysExtractionMethod());
            return $extractor;
        });

        $app['lexik_form_filter.filter_prepare'] = $app->share(function () {
            return new PrepareListener();
        });

        // Subscriber to get filter condition
        $app['lexik_form_filter.get_filter.doctrine_orm'] = $app->share(function () {
            return new DoctrineORMSubscriber();
        });

        $app['lexik_form_filter.get_filter.doctrine_dbal'] = $app->share(function () {
            return new DoctrineDBALSubscriber();
        });

        // Listener to apply filter conditions
        $app['lexik_form_filter.apply_filter.doctrine_orm'] = $app->share(function () {
            return new DoctrineApplyFilterListener(null);
        });

        $app['lexik_form_filter.apply_filter.doctrine_dbal'] = $app->share(function () {
            return new DoctrineApplyFilterListener(null);
        });
    }

    /**
     * @param Application $app
     */
    public function boot(Application $app)
    {
        $app['dispatcher']->addSubscriber($app['lexik_form_filter.get_filter.doctrine_orm']);
        $app['dispatcher']->addSubscriber($app['lexik_form_filter.get_filter.doctrine_dbal']);
        $app['dispatcher']->addListener('lexik_filter.prepare', [$app['lexik_form_filter.filter_prepare'], 'onFilterBuilderPrepare']);
        $app['dispatcher']->addListener('lexik_filter.apply_filters.orm', [$app['lexik_form_filter.apply_filter.doctrine_orm'], 'onApplyFilterCondition']);
        $app['dispatcher']->addListener('lexik_filter.apply_filters.dbal', [$app['lexik_form_filter.apply_filter.doctrine_dbal'], 'onApplyFilterCondition']);
    }
}
