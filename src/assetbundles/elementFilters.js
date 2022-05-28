$(document).ready(function(){

if (typeof Craft.elementIndex != 'undefined') {

// on page load
addFilters(getDataKey($('#sidebar [data-key].sel')));

// on changing source
$('#sidebar [data-key]').on('click', function(){
  sourceKey = getDataKey($(this));
  addFilters(sourceKey);
});

function getDataKey(element){
    
    var dataKey = null;
    // assets, with subfolders
    // use volume handle instead datakey, because datakey would be different for each folder
    if(getElementType() == 'assets'){
      // main folder
      if(element.is("[data-volume-handle]")){
          dataKey = element.attr('data-key');
      }else if(element.attr('data-key').includes('custom:')){
      /// custom source
          dataKey = element.attr('data-key');
      }else{
      // subfolder
          dataKey = element.parents('li.expanded').last().find('[data-volume-handle]').first().attr('data-key');
      }


    // commerce orders
    }else if(getElementType() == 'orders'){
      dataKey = 'all';
    // rest elements
    }else{
        dataKey = element.attr('data-key');
    }
    return dataKey;
}


function getElementType(){

  var url = window.location.pathname;
  var cpTrigger = Craft.cpTrigger;

  var elementType = null;

  if(url.includes(cpTrigger + '/' + 'entries')){
      var elementType = 'entries';
  }

  if(url.includes(cpTrigger + '/' + 'categories')){
      var elementType = 'categories';
  }

  if(url.includes(cpTrigger + '/' + 'users')){
      var elementType = 'users';
  }

  if(url.includes(cpTrigger + '/' + 'assets')){
      var elementType = 'assets';
  }

  if(url.includes(cpTrigger + '/' + 'commerce/orders')){
      var elementType = 'orders';
  }

  if(url.includes(cpTrigger + '/' + 'commerce/products')){
      var elementType = 'products';
  }

  return elementType;

}


function addFilters(sourceKey){
  var elementType = getElementType();

  // cannot use * in url
  if(sourceKey == '*'){
    sourceKey = 'all';
  }

  if(elementType == null){
    return;
  }

  // insert link to list
  if(userCanManageFilters){
      $('.element-filters-settings').remove();
      var linkUrl = Craft.getCpUrl('quick-filters/' + elementType + '/' +  sourceKey);
      $('<a href="'+ linkUrl +'" class="customize-sources element-filters-settings" type="button"><span class="element-filters-settings__icon" data-icon="tool"></span><span class="label">Filters</span></a>').appendTo('#sidebar');    
  }

}

function initSelect(element){

        var placeholder = element.attr('data-element-filters-select-placeholder');
        var searchPlaceholder = element.attr('data-element-filters-search-select-placeholder');

        // dont add search to switch
        if(element.attr('data-element-filters-type') == 'switch' ){
          var showSearch = false;
        }else{
          var showSearch = true;
        }


        new SlimSelect({
          select: element[0],
          placeholder: placeholder,
          searchPlaceholder: searchPlaceholder,
          showSearch: showSearch,
        });

}

function initWidgets(){
   // select
    $('[data-element-filters-select]').each(function(){

        // on assets list event runs two times, we need to avoid initializing on already inittialized select
        if(typeof $(this)[0].slim === 'object'){
          return;
        }

        initSelect($(this));

    });

    // datepicker
    $('[data-element-filters-datepicker]').each(function(){
        // var format = 'DD-MM-YYYY';
        var format = $(this).attr('data-element-filters-datepicker-format');
        var firstDay = parseInt($(this).attr('data-element-filters-datepicker-firstday'));
        var cancelLabel = $(this).attr('data-element-filters-datepicker-cancel');
        var applyLabel = $(this).attr('data-element-filters-datepicker-apply');
        var daysOfWeek = JSON.parse($(this).attr('data-element-filters-datepicker-weekdays'));
        var monthNames = JSON.parse($(this).attr('data-element-filters-datepicker-months'));
        
        $(this).daterangepicker({
          opens: 'left',
          autoUpdateInput: false,
          locale: {
            "format": format,
            "firstDay": firstDay,
            "cancelLabel": cancelLabel,
            "applyLabel": applyLabel,
            "daysOfWeek": daysOfWeek,
            "monthNames": monthNames,
          }
        });

        // set valiue after reload
        if($(this).attr('data-element-filters-datepicker-start') !== undefined){
            var start = $(this).attr('data-element-filters-datepicker-start');
            var end = $(this).attr('data-element-filters-datepicker-end');
            console.log(start)
            $(this).data('daterangepicker').setStartDate(moment(start).format("MM-DD-YYYY"));
            $(this).data('daterangepicker').setEndDate(moment(end).format("MM-DD-YYYY"));      
            var format = $(this).attr('data-element-filters-datepicker-format');
            $(this).val(moment(start).format(format) + ' - ' + moment(end).format(format));                              
        }

    });

    // set text input value
    $('[data-element-filters-datepicker]').on('apply.daterangepicker', function(ev, picker) {
        // var format = 'DD-MM-YYYY';
        var format = $(this).attr('data-element-filters-datepicker-format');
        $(this).val(picker.startDate.format(format) + ' - ' + picker.endDate.format(format));
    });

    // clear on cancel
    $('[data-element-filters-datepicker]').on('cancel.daterangepicker', function(ev, picker) {
        $(this).data('daterangepicker').setStartDate(moment().format("MM-DD-YYYY"));
        $(this).data('daterangepicker').setEndDate(moment().format("MM-DD-YYYY"));
        $(this).val('');
        Craft.elementIndex.updateElements();
    });  
}

// we check for sourcekey because on asset list event fired two times
Craft.elementIndex.on('updateElements', function(event) {
    initWidgets();
});


// trigger refresh
$('body').on('change', '[data-element-filters-select]', function(){
  Craft.elementIndex.updateElements();
});

$('body').on('apply.daterangepicker', '[data-element-filters-datepicker]', function(ev, picker) {
  Craft.elementIndex.updateElements();
});

$('body').on('change', '[data-elements-filters-range-input]', function(){
  Craft.elementIndex.updateElements();
});

$('body').on('change', '[data-elements-filters-text]', function(){
  Craft.elementIndex.updateElements();
});

// modify params
Craft.elementIndex.on('registerViewParams', function(event) {

  // selects
  $('[data-element-filters-select]').each(function(){
    var select = $(this)
    if (select.val() != '') {
 
      // options, relations
      if(select.attr('data-element-filters-type') == 'options' || select.attr('data-element-filters-type') == 'relation'){
        var handle = select.attr('data-element-filters-handle');
        event.params.criteria[handle] = select.val();
      }

      // switch
      if(select.attr('data-element-filters-type') == 'switch'){
        var handle = select.attr('data-element-filters-handle');
        if(select.val() == 1){
          var switchVal = true;
          event.params.criteria[handle] = switchVal;
        }else if(select.val() == 0){
          var switchVal = false;
          event.params.criteria[handle] = switchVal;
        }
        
      }

    }
  });

  // datepicker
  $('[data-element-filters-datepicker]').each(function(){
    if($(this).val() != ''){
      handle = $(this).attr('data-element-filters-handle');
      var start = $(this).data('daterangepicker').startDate.startOf('day').format('YYYY-MM-DD HH:mm:ss');
      var end = $(this).data('daterangepicker').endDate.endOf('day').format('YYYY-MM-DD HH:mm:ss');
      event.params.criteria[handle] = ['and', '>= ' + start, '<= ' + end];
    }
  });

  // range
  $('[data-elements-filters-range-input]').each(function(){
    var min = null;
    var max = null;

    if($(this).is('[data-elements-filters-range-min]')){
      min = $(this).val();
      var maxInput = $(this).closest('[data-elements-filters-range]').find('[data-elements-filters-range-max]');
      if(maxInput.val() != ''){
        max = maxInput.val();
      }
    }

    if($(this).is('[data-elements-filters-range-max]')){
      max = $(this).val();
      var minInput = $(this).closest('[data-elements-filters-range]').find('[data-elements-filters-range-min]');
      if(minInput.val() != ''){
        min = minInput.val();
      }
    }
    // console.log($(this).closest('data-elements-filters-range'))
    var handle = $(this).closest('[data-elements-filters-range]').attr('data-element-filters-handle');
    
    if(min == ''){
      min = null;
    }
    if(max == ''){
      max = null;
    }

    if(min != null && max == null){
      event.params.criteria[handle] = ['and', '>= ' + min];
    }
    if(min == null && max != null){
      event.params.criteria[handle] = ['and', '<= ' + max];
    }
    if(min != null && max != null){
      event.params.criteria[handle] = ['and', '>= ' + min, '<= ' + max];
    }    

    
  });

  // text
  $('[data-elements-filters-text]').each(function(){
    var handle = $(this).attr('data-element-filters-handle');
    var value = $(this).val()
    if(value != ''){
      event.params.criteria[handle] = '*' + value + '*';
    }
    
  });  


});


// clear filters
$('body').on('click', '[data-element-filter-clear]', function(){
  
    var handle = $(this).attr('data-element-filter-clear-handle');
    console.log(handle)

    // range
    $('[data-element-filters-handle="' + handle + '"]').find('[data-elements-filters-range-input]').val('');

    //text
    $('[data-element-filters-handle="' + handle + '"][data-elements-filters-text]').val('');

    // datepicker
    var pickerElement = $('[data-element-filters-datepicker][data-element-filters-handle="' + handle + '"]');
    if(pickerElement.length){
        pickerElement.data('daterangepicker').setStartDate(moment().format("MM-DD-YYYY"));
        pickerElement.data('daterangepicker').setEndDate(moment().format("MM-DD-YYYY"));
        pickerElement.val('');
    }


    // dropdown
    var dropdownElement = $('[data-element-filters-select][data-element-filters-handle="' + handle + '"]');
    if(dropdownElement.length){
        // we need to destroy slim select because if we change its value programitically and refresh craft element list, class "busy" does not appear on list
        dropdownElement[0].slim.destroy();
        // dropdownElement[0].slim.set([]);
        dropdownElement.find("option:selected").prop("selected", false);
        initSelect(dropdownElement);
    }
    

    Craft.elementIndex.updateElements();

});


}

});
