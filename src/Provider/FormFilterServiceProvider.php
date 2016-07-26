<?php

namespace Sergiors\Silex\Provider;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Application;
use Silex\Api\BootableProviderInterface;
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
class FormFilterServiceProvider implements ServiceProviderInterface, BootableProviderInterface
{
    /**
     * @param Container $app
     */
    public function register(Container $app)
    {
        if (!isset($app['form.factory'])) {
            throw new \LogicException(
                'You must register the FormServiceProvider to use the FormFilterServiceProvider.'
            );
        }

        $app['lexik_form_filter.query_builder_updater'] = function () use ($app) {
            return new FilterBuilderUpdater(
                $app['lexik_form_filter.form_data_extractor'],
                $app['dispatcher']
            );
        };

        // Alias
        $app['form_filter'] = function () use ($app) {
            return $app['lexik_form_filter.query_builder_updater'];
        };

        // Filter Types
        $app['form.types'] = $app->extend('form.types', function ($types) use ($app) {
            $types = array_merge($types, [
                new Type\TextFilterType(),
                new Type\NumberFilterType(),
                new Type\NumberRangeFilterType(),
                new Type\CheckboxFilterType(),
                new Type\BooleanFilterType(),
                new Type\ChoiceFilterType(),
                new Type\DateFilterType(),
                new Type\DateRangeFilterType(),
                new Type\DateTimeFilterType(),
                new Type\DateTimeRangeFilterType(),
                new Type\CollectionAdapterFilterType(),
                new Type\SharedableFilterType()
            ]);

            if (isset($app['doctrine'])) {
                $types[] = new Type\EntityFilterType($app['doctrine']);
            }

            return $types;
        });

        // Type extension
        $app['form.type.extensions'] = $app->extend('form.type.extensions', function ($extensions) {
            $extensions[] = new FilterTypeExtension();

            return $extensions;
        });

        // Form data extraction
        $app['lexik_form_filter.form_data_extractor'] = function () {
            $extractor = new FormDataExtractor();
            $extractor->addMethod(new DefaultExtractionMethod());
            $extractor->addMethod(new TextExtractionMethod());
            $extractor->addMethod(new ValueKeysExtractionMethod());

            return $extractor;
        };

        $app['lexik_form_filter.filter_prepare'] = function () {
            return new PrepareListener();
        };

        // Subscriber to get filter condition
        $app['lexik_form_filter.get_filter.doctrine_orm'] = function () {
            return new DoctrineORMSubscriber();
        };

        $app['lexik_form_filter.get_filter.doctrine_dbal'] = function () {
            return new DoctrineDBALSubscriber();
        };

        // Listener to apply filter conditions
        $app['lexik_form_filter.apply_filter.doctrine_orm'] = function () {
            return new DoctrineApplyFilterListener(null);
        };

        $app['lexik_form_filter.apply_filter.doctrine_dbal'] = function () {
            return new DoctrineApplyFilterListener(null);
        };
    }

    /**
     * @param Application $app
     */
    public function boot(Application $app)
    {
        $app['dispatcher']->addSubscriber($app['lexik_form_filter.get_filter.doctrine_orm']);
        $app['dispatcher']->addSubscriber($app['lexik_form_filter.get_filter.doctrine_dbal']);

        $app['dispatcher']->addListener('lexik_filter.prepare', [
            $app['lexik_form_filter.filter_prepare'],
            'onFilterBuilderPrepare',
        ]);
        $app['dispatcher']->addListener('lexik_filter.apply_filters.orm', [
            $app['lexik_form_filter.apply_filter.doctrine_orm'],
            'onApplyFilterCondition',
        ]);
        $app['dispatcher']->addListener('lexik_filter.apply_filters.dbal', [
            $app['lexik_form_filter.apply_filter.doctrine_dbal'],
            'onApplyFilterCondition',
        ]);
    }
}
