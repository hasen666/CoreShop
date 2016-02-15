/**
 * CoreShop
 *
 * LICENSE
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2015 Dominik Pfaffenbauer (http://dominik.pfaffenbauer.at)
 * @license    http://www.coreshop.org/license     GNU General Public License version 3 (GPLv3)
 */

pimcore.registerNS("pimcore.plugin.coreshop.indexes.fields");

pimcore.plugin.coreshop.indexes.fields = Class.create({
    data: {},
    brickKeys: [],

    initialize: function (data) {
        this.data = data;
        this.config = data.config;
    },

    getLayout : function() {
        this.configPanel = new Ext.Panel({
            layout: "border",
            items: [this.getSelectionPanel(), this.getClassDefinitionTreePanel()]

        });

        return this.configPanel;
    },

    getData: function () {

        var data = {};
        if(this.languageField) {
            data.language = this.languageField.getValue();
        }

        if(this.selectionPanel) {
            data.columns = [];
            this.selectionPanel.getRootNode().eachChild(function(child) {
                var obj = Ext.Object.merge(child.data, {}); //Removes unneeded nested data

                data.columns.push(obj);
            }.bind(this));
        }

        return data;
    },

    getSelectionPanel: function () {
        if(!this.selectionPanel) {

            var childs = [];
            if(this.config.hasOwnProperty("columns")) {
                for (var i = 0; i < this.config.columns.length; i++) {
                    var nodeConf = this.config.columns[i];
                    var child = Ext.Object.merge(nodeConf,
                        {
                            text: nodeConf.name,
                            type: "data",
                            leaf: true,
                            iconCls: "pimcore_icon_" + nodeConf.dataType
                        }
                    );

                    childs.push(child);
                }
            }

            this.selectionPanel = new Ext.tree.TreePanel({
                root: {
                    id: "0",
                    root: true,
                    text: t("coreshop_indexes_selected_fields"),
                    leaf: false,
                    isTarget: true,
                    expanded: true,
                    children: childs
                },

                viewConfig: {
                    plugins: {
                        ptype: 'treeviewdragdrop',
                        ddGroup: "columnconfigelement"
                    },
                    listeners: {
                        beforedrop: function (node, data, overModel, dropPosition, dropHandlers, eOpts) {
                            var target = overModel.getOwnerTree().getView();
                            var source = data.view;

                            if (target != source) {
                                var record = data.records[0];

                                if (this.selectionPanel.getRootNode().findChild("key", record.data.key)) {
                                    dropHandlers.cancelDrop();
                                } else {
                                    var copy = record.createNode(Ext.apply({}, record.data));

                                    var element = this.getConfigElement(copy);
                                    element.getConfigDialog(copy);

                                    data.records = [copy]; // assign the copy as the new dropNode
                                }
                            }
                        }.bind(this),
                        options: {
                            target: this.selectionPanel
                        }
                    }
                },
                region:'east',
                title: t('coreshop_indexes_selected_fields'),
                layout:'fit',
                width: 428,
                split:true,
                autoScroll: true,
                listeners:{
                    itemcontextmenu: this.onTreeNodeContextmenu.bind(this)
                }
            });
            var store = this.selectionPanel.getStore();
            var model = store.getModel();
            model.setProxy({
                type: 'memory'
            });
        }

        return this.selectionPanel;
    },

    onTreeNodeContextmenu: function (tree, record, item, index, e, eOpts ) {
        e.stopEvent();

        tree.select();

        var menu = new Ext.menu.Menu();

        if (this.id != 0) {
            menu.add(new Ext.menu.Item({
                text: t('delete'),
                iconCls: "pimcore_icon_delete",
                handler: function(node) {
                    this.selectionPanel.getRootNode().removeChild(record, true);
                }.bind(this, record)
            }));
            menu.add(new Ext.menu.Item({
                text: t('edit'),
                iconCls: "pimcore_icon_edit",
                handler: function(node) {
                    this.getConfigElement(record).getConfigDialog(record);
                }.bind(this, record)
            }));
        }

        menu.showAt(e.pageX, e.pageY);
    },

    getConfigElement: function(record) {
        var element = null;

        if(record.data.objectType) {
            if(pimcore.plugin.coreshop.indexes.objecttype[this.data.type][record.data.objectType]) {
                element = new pimcore.plugin.coreshop.indexes.objecttype[this.data.type][record.data.objectType]();
            }
        }

        return element;
    },

    /*
    *       FIELD-TREE
    *
    * */

    getClassDefinitionTreePanel: function () {
        if (!this.classDefinitionTreePanel) {
            this.brickKeys = [];
            this.classDefinitionTreePanel = this.getClassTree("/plugin/CoreShop/admin_Indexes/get-class-definition-for-field-selection", this.data.classId);
        }

        return this.classDefinitionTreePanel;
    },

    getClassTree: function(url, classId) {

        var tree = new Ext.tree.TreePanel({
            title: t('class_definitions'),
            region: "center",
            //ddGroup: "columnconfigelement",
            autoScroll: true,
            rootVisible: false,
            root: {
                id: "0",
                root: true,
                text: t("base"),
                allowDrag: false,
                leaf: true,
                isTarget: true
            },
            viewConfig: {
                plugins: {
                    ptype: 'treeviewdragdrop',
                    enableDrag: true,
                    enableDrop: false,
                    ddGroup: "columnconfigelement"
                }
            }
        });

        Ext.Ajax.request({
            url: url,
            params: {
                id: classId
            },
            success: this.initLayoutFields.bind(this, tree)
        });


        tree.addListener("itemdblclick", function(tree, record, item, index, e, eOpts ) {
            if(!record.data.root && record.datatype != "layout"
                && record.data.dataType != 'localizedfields') {
                var copy = Ext.apply({}, record.data);

                if(this.selectionPanel && !this.selectionPanel.getRootNode().findChild("key", record.data.key)) {
                    this.selectionPanel.getRootNode().appendChild(copy);
                }

                if (record.data.dataType == "keyValue") {
                    var ccd = new pimcore.object.keyvalue.columnConfigDialog();
                    ccd.getConfigDialog(copy, this.selectionPanel);
                }
            }
        }.bind(this));

        return tree;
    },

    initLayoutFields : function(tree, response) {
        var data = Ext.decode(response.responseText);

        var keys = Object.keys(data);
        for(var i = 0; i < keys.length; i++) {
            if (data[keys[i]]) {
                if (data[keys[i]].childs) {
                    var text = t(data[keys[i]].nodeLabel);

                    if(data[keys[i]].nodeType == "objectbricks") {
                        text = ts(data[keys[i]].nodeLabel) + " " + t("columns");
                    }

                    if(data[keys[i]].nodeType == "classificationstore") {
                        text = ts(data[keys[i]].nodeLabel) + " " + t("columns");
                    }

                    var baseNode = {
                        type: "layout",
                        allowDrag: false,
                        iconCls: "pimcore_icon_" + data[keys[i]].nodeType,
                        text: text
                    };

                    baseNode = tree.getRootNode().appendChild(baseNode);
                    for (var j = 0; j < data[keys[i]].childs.length; j++) {
                        var node = this.addDataChild.call(baseNode, data[keys[i]].childs[j].fieldtype, data[keys[i]].childs[j], data[keys[i]].nodeType, data[keys[i]].class);

                        baseNode.appendChild(node);
                    }
                    if(data[keys[i]].nodeType == "object") {
                        baseNode.expand();
                    } else {
                        baseNode.collapse();
                    }
                }
            }
        }
    },


    addDataChild: function (type, initData, objectType, className) {

        if(type != "objectbricks" && !initData.invisible) {
            var isLeaf = true;
            var draggable = true;

            var key = initData.name;

            var newNode = Ext.Object.merge(initData, {
                text :  key,
                key : initData.name,
                type : "data",
                layout : initData,
                leaf : isLeaf,
                allowDrag : draggable,
                dataType : type,
                iconCls: "pimcore_icon_" + type,
                expanded: true,
                objectType : objectType
            });

            newNode = this.appendChild(newNode);

            if(this.rendered) {
                this.expand();
            }

            return newNode;
        } else {
            return null;
        }

    }
});