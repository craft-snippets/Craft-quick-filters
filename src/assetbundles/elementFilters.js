(function(){

// if (typeof elementIndexObject != 'undefined') {

// on page load
$(document).ready(function(){
    addFilters(getDataKey($('#sidebar [data-key].sel')));
})

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
      $('<a href="'+ linkUrl +'" class="customize-sources element-filters-settings btn edit icon" type="button"><span class="label">Filters</span></a>').appendTo('#sidebar');
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


    $('[data-elements-filters-date-input]').each(function(){

        var format = $(this).closest('[data-element-filters-date-format]').attr('data-element-filters-date-format');

        var val = $(this).attr('data-initial-value');
        if(val){
          val = moment(val, 'YYYY-MM-DD').format(format);
          $(this).val(val)
        }

        // init datepicker
        $(this).datepicker($.extend({
            defaultDate: new Date()
        }, Craft.datepickerOptions));

    });

}

// we check for sourcekey because on asset list event fired two times

Garnish.on(Craft.BaseElementIndex, 'updateElements', (ev) => {
    initWidgets();
});


// trigger refresh
$('body').on('change', '[data-element-filters-select]', function(){
  elementIndexObject.updateElements();
});

$('body').on('change', '[data-elements-filters-range-input], [data-elements-filters-date-input]', function(){
  elementIndexObject.updateElements();
});

$('body').on('change', '[data-elements-filters-text]', function(){
  elementIndexObject.updateElements();
});


elementIndexObject = null;

// modify params
  Garnish.on(Craft.BaseElementIndex, 'registerViewParams', (event) => {

  elementIndexObject = event.target;

  // selects
  $('[data-element-filters-select]').each(function(){
    var select = $(this)
    if (select.val() != '') {
 
      // options, relations, color
      if(select.attr('data-element-filters-type') == 'options' || select.attr('data-element-filters-type') == 'relation' || select.attr('data-element-filters-type') == 'color'){
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


  $('[data-elements-filters-date-input]').each(function(){
    var min = null;
    var max = null;

    if($(this).is('[data-elements-filters-date-min]')){
      min = $(this).val();
      var maxInput = $(this).closest('[data-elements-filters-date]').find('[data-elements-filters-date-max]');
      if(maxInput.val() != ''){
        max = maxInput.val();
      }
    }

    if($(this).is('[data-elements-filters-date-max]')){
      max = $(this).val();
      var minInput = $(this).closest('[data-elements-filters-date]').find('[data-elements-filters-date-min]');
      if(minInput.val() != ''){
        min = minInput.val();
      }
    }
    var handle = $(this).closest('[data-elements-filters-date]').attr('data-element-filters-handle');

    if(min == ''){
      min = null;
    }
    if(max == ''){
      max = null;
    }

    // format
    var format = $(this).closest('[data-elements-filters-date]').attr('data-element-filters-date-format');

    if(min){
      min = moment(min, format).format("YYYY-MM-DD");
    }
    if(max){
      max = moment(max, format).format("YYYY-MM-DD");
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
  // jquery wont work with modal
  document.body.addEventListener("click",function(e){

    if(!e.target.matches('[data-element-filter-clear]')){
      return;
    }

    var handle = $(e.target).attr('data-element-filter-clear-handle');

    // range
    $('[data-element-filters-handle="' + handle + '"]').find('[data-elements-filters-range-input]').val('');

    //text
    $('[data-element-filters-handle="' + handle + '"][data-elements-filters-text]').val('');

    $('[data-element-filters-handle="' + handle + '"]').find('[data-elements-filters-date-input]').val('');


    // dropdown
    var dropdownElement = $('[data-element-filters-select][data-element-filters-handle="' + handle + '"]');
    if(dropdownElement.length){
        // we need to destroy slim select because if we change its value programitically and refresh craft element list, class "busy" does not appear on list
        dropdownElement[0].slim.destroy();
        dropdownElement.find("option:selected").prop("selected", false);
        initSelect(dropdownElement);
    }
    

    elementIndexObject.updateElements();

}, true);


// }

})();
