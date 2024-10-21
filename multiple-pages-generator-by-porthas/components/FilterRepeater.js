import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { Button, SelectControl, TextControl, IconButton } from '@wordpress/components';
import { plus, close, chevronDown, chevronUp } from '@wordpress/icons';

const FilterRepeater = ({ 
    conditions, 
    updateConditions, 
    operators, 
    CompareOperators,
    labels = {}, // New prop for configurable labels
    columnOptions // Add this prop
}) => {
    const [expandedFilters, setExpandedFilters] = useState([]);

    // Default labels
    const defaultLabels = {
        applyFilters: __('How to Apply Filters', 'multi-pages-plugin'),
        matchAll: __('Show items that match all filters', 'multi-pages-plugin'),
        matchAny: __('Show items that match any filter', 'multi-pages-plugin'),
        emptyFilter: __('Empty filter', 'multi-pages-plugin'),
        collapse: __('Collapse', 'multi-pages-plugin'),
        expand: __('Expand', 'multi-pages-plugin'),
        removeFilter: __('Remove filter', 'multi-pages-plugin'),
        column: __('Column', 'multi-pages-plugin'),
        selectColumn: __('Select a column...', 'multi-pages-plugin'),
        operator: __('Operator', 'multi-pages-plugin'),
        value: __('Value', 'multi-pages-plugin'),
        addFilter: __('Add filter', 'multi-pages-plugin'),
    };

    // Merge default labels with provided labels
    const mergedLabels = { ...defaultLabels, ...labels };

    const toggleFilterExpansion = (index) => {
        setExpandedFilters(prev => {
            const newExpanded = [...prev];
            newExpanded[index] = !newExpanded[index];
            return newExpanded;
        });
    };

    const addCondition = () => {
        if (conditions.conditions.length < 10) {
            const newIndex = conditions.conditions.length;
            updateConditions({
                ...conditions,
                conditions: [...conditions.conditions, { column: '', operator: operators[0].value, value: '' }]
            });
            // Expand the newly added filter
            setExpandedFilters(prev => {
                const newExpanded = [...prev];
                newExpanded[newIndex] = true;
                return newExpanded;
            });
        }
    };

    const removeCondition = (index) => {
        const newConditions = [...conditions.conditions];
        newConditions.splice(index, 1);
        updateConditions({ 
            ...conditions,
            conditions: newConditions
        });
    };

    const updateCondition = (index, field, value) => {
        const newConditions = [...conditions.conditions];
        newConditions[index][field] = value;
        updateConditions({ 
            ...conditions,
            conditions: newConditions
        });
    };

    const updateLogic = (logic) => {
        updateConditions({
            ...conditions,
            logic
        });
    };

    const getFilterSummary = (condition) => {
        if (!condition.column) return mergedLabels.emptyFilter;
        
        let summary = `${condition.column}`;
        if (condition.operator) {
            summary += ` ${getOperatorLabel(condition.operator).toLowerCase()}`;
            if (CompareOperators.includes(condition.operator) && condition.value) {
                summary += ` ${condition.value}`;
            }
        }
        
        const shortSummary = summary.length > 15 ? summary.substring(0, 12) + '...' : summary;
        return { short: shortSummary, full: summary };
    };

    // Function to get operator label from value
    const getOperatorLabel = (operatorValue) => {
        const operator = operators.find(op => op.value === operatorValue);
        return operator ? operator.label : operatorValue;
    };
    return (
        <>
            <SelectControl
                label={mergedLabels.applyFilters}
                value={conditions.logic}
                options={[
                    { label: mergedLabels.matchAll, value: 'all' }, 
                    { label: mergedLabels.matchAny, value: 'any' },
                ]}
                onChange={updateLogic} 
            />
            {conditions.conditions.map((condition, index) => (
                <div key={index} style={{ marginBottom: '10px', padding: '0px 10px', border: '1px solid #ccc' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
                        <strong 
                            title={getFilterSummary(condition).full}
                        >
                            {getFilterSummary(condition).short}
                        </strong>
                        <div>
                            <IconButton
                                icon={expandedFilters[index] ? chevronUp : chevronDown}
                                label={expandedFilters[index] ? mergedLabels.collapse : mergedLabels.expand}
                                onClick={() => toggleFilterExpansion(index)}
                            />
                            <IconButton
                                icon={close}
                                label={mergedLabels.removeFilter}
                                onClick={() => removeCondition(index)}
                            />
                        </div>
                    </div>
                    {expandedFilters[index] && (
                        <>
                            {columnOptions ? (
                                <SelectControl
                                    label={mergedLabels.column}
                                    value={condition.column}
                                    options={[
                                        { value: '', label: mergedLabels.selectColumn },
                                        ...columnOptions
                                    ]}
                                    onChange={(value) => updateCondition(index, 'column', value)}
                                />
                            ) : ( 
                                <TextControl
                                    label={mergedLabels.column}
                                    value={condition.column}
                                    onChange={(value) => updateCondition(index, 'column', value)}
                                />
                            )}
                            <SelectControl
                                label={mergedLabels.operator}
                                value={condition.operator}
                                options={operators}
                                onChange={(value) => updateCondition(index, 'operator', value)}
                            />
                            {CompareOperators.includes(condition.operator) && (
                                <TextControl 
                                    label={mergedLabels.value}
                                    value={condition.value}
                                    onChange={(value) => updateCondition(index, 'value', value)}
                                />
                            )}
                        </>
                    )}
                </div>
            ))}
            {conditions.conditions.length < 10 && (
                <Button
                    isSecondary
                    onClick={addCondition}
                    icon={plus}
                >
                    {mergedLabels.addFilter}
                </Button>
            )}
        </>
    );
};

export default FilterRepeater;
